<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Booking\BookingLog;
use App\Domain\Booking\BookingService;
use App\Domain\Booking\TravelDates;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\BookingUpdate;
use App\Models\Payment;
use App\Support\Csv;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly BookingLog $log,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        $bookings = $this->query($filters)
            ->with('package.banner')
            ->when($filters['sort'] ?? 'newest', fn ($q, $sort) => match ($sort) {
                'oldest' => $q->oldest('id'),
                'total_desc' => $q->orderByDesc('total')->latest('id'),
                'balance_desc' => $q->orderByRaw('(total - paid_amount) desc')->latest('id'),
                default => $q->latest('id'),
            })
            ->paginate($filters['per_page'] ?? 20);

        return response()->json([
            'data' => collect($bookings->items())->map(fn (Booking $b) => $this->summary($b)),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
                'from' => $bookings->firstItem(),
                'to' => $bookings->lastItem(),
            ],
            'counts' => Booking::query()->toBase()->select('status', DB::raw('count(*) as bookings_count'))->groupBy('status')->pluck('bookings_count', 'status')->map(fn ($n) => (int) $n),
            'statuses' => Booking::STATUSES,
        ]);
    }

    public function show(Booking $booking): JsonResponse
    {
        $wasUnread = $booking->read_at === null;

        if ($wasUnread) {
            $booking->forceFill(['read_at' => now()])->save();
        }

        return response()->json(['data' => $this->detail($booking), 'was_unread' => $wasUnread]);
    }

    /**
     * Change the status and internal notes. A new status or a message to the
     * customer goes into the customer's history and, unless staff untick
     * "Email the customer", out to them by email with the latest details.
     */
    public function update(Request $request, Booking $booking): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Booking::STATUSES)), $this->statusFollowsPayments($booking)],
            'admin_notes' => ['nullable', 'string', 'max:10000'],
            'customer_message' => ['nullable', 'string', 'max:2000'],
            'notify' => ['sometimes', 'boolean'],
        ]);

        $before = $booking->status;
        $notesBefore = (string) $booking->admin_notes;
        $booking->update(['status' => $data['status'], 'admin_notes' => $data['admin_notes'] ?? null]);
        $message = trim((string) ($data['customer_message'] ?? '')) ?: null;
        $notify = $request->boolean('notify', true) && $booking->customer_email;

        if ($booking->status !== $before) {
            $this->log->record($booking, 'status_changed', ['from' => $before, 'to' => $booking->status, 'notify' => (bool) $notify], $request->user());
        }

        if ((string) $booking->admin_notes !== $notesBefore) {
            $this->log->record($booking, 'notes_changed', ['from' => $notesBefore, 'to' => (string) $booking->admin_notes], $request->user());
        }

        if ($message) {
            $this->log->record($booking, 'message_sent', ['message' => $message, 'notify' => (bool) $notify], $request->user());
        }

        if ($booking->status !== $before || $message) {
            $this->bookings->recordUpdate($booking, $booking->status !== $before ? 'status' : 'message', $message, $request->boolean('notify', true), $request->user());
        }

        return response()->json(['data' => $this->detail($booking->refresh())]);
    }

    public function destroy(Booking $booking): JsonResponse
    {
        DB::transaction(function () use ($booking) {
            // To the Trash with its documents; the files stay on the private disk.
            $booking->documents->each->delete();
            $booking->delete();
        });

        return response()->json(status: 204);
    }

    /**
     * Record a payment received at the office (bank, mobile banking, cash).
     */
    public function storePayment(Request $request, Booking $booking): JsonResponse
    {
        // A payment can never be more than what is still owed. The total is
        // fixed when the booking is made, so this balance cannot go stale.
        if ($booking->balance() <= 0) {
            throw ValidationException::withMessages(['amount' => 'This booking is already paid in full.']);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:'.$booking->balance()],
            'method' => ['required', Rule::in(array_keys(Payment::OFFLINE_METHODS))],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
            'customer_message' => ['nullable', 'string', 'max:2000'],
            'notify' => ['sometimes', 'boolean'],
        ], [
            'amount.max' => 'Only '.Money::format($booking->balance(), 'en').' is still due on this booking.',
        ]);

        $payment = DB::transaction(function () use ($booking, $data, $request) {
            // Re-check against a locked row: two people recording at once
            // would otherwise both pass the rule above.
            $fresh = Booking::whereKey($booking->id)->lockForUpdate()->first();

            if ((float) $data['amount'] > $fresh->balance()) {
                throw ValidationException::withMessages([
                    'amount' => 'Only '.Money::format($fresh->balance(), 'en').' is still due on this booking.',
                ]);
            }

            $payment = $booking->payments()->create([
                ...collect($data)->except(['customer_message', 'notify'])->all(),
                'is_online' => false,
                'status' => 'paid',
                'currency' => $booking->currency,
                'recorded_by' => $request->user()->id,
            ]);

            $this->bookings->refreshPayments($booking);

            return $payment;
        });

        $this->log->record($booking, 'payment_recorded', [
            'amount' => (float) $payment->amount,
            'method' => $payment->methodLabel(),
            'reference' => $payment->reference,
            'note' => $payment->note,
            'paid_at' => $payment->paid_at?->toIso8601String(),
        ], $request->user());

        // A payment the customer started online and never finished could still be confirmed by the gateway and overpay.
        $this->bookings->withdrawUnfinishedPayments($booking, $request->user());

        // Receipt to the customer with the new balance (unless staff untick "Email the customer").
        $message = trim((string) ($data['customer_message'] ?? '')) ?: null;
        $this->bookings->recordPayment($payment, $request->boolean('notify', true), $request->user(), $message);

        return response()->json(['data' => $this->detail($booking->refresh())], 201);
    }

    /**
     * Remove an office payment recorded by mistake. Online payments stay.
     */
    public function destroyPayment(Request $request, Booking $booking, Payment $payment): JsonResponse
    {
        abort_unless($payment->booking_id === $booking->id, 404);
        abort_if($payment->is_online, 422, 'Online payments cannot be removed.');

        DB::transaction(function () use ($booking, $payment, $request) {
            $payment->delete();
            $this->log->record($booking, 'payment_removed', [
                'amount' => (float) $payment->amount,
                'method' => $payment->methodLabel(),
                'reference' => $payment->reference,
            ], $request->user());
            // The status follows what is still paid (see BookingService::refreshPayments).
            $this->bookings->refreshPayments($booking);
        });

        return response()->json(['data' => $this->detail($booking->refresh())]);
    }

    /**
     * Stream a private booking document to an authorized admin.
     */
    public function document(Request $request, Booking $booking, BookingDocument $document): StreamedResponse
    {
        abort_unless($document->booking_id === $booking->id, 404);

        $this->log->record($booking, 'document_opened', ['name' => $document->original_name, 'kind' => BookingDocument::KINDS[$document->kind] ?? $document->kind], $request->user());

        return Storage::disk(BookingDocument::DISK)->response($document->path, $document->original_name, [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => 'sandbox',
        ], 'inline');
    }

    /**
     * CSV of the current filter (UTF-8 BOM so Excel shows Bangla correctly).
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);

        return response()->streamDownload(function () use ($filters) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Reference', 'Date', 'Status', 'Package', 'Option', 'Travelers', 'Travel date', 'Total', 'Advance', 'Paid', 'Balance', 'Name', 'Phone', 'Email']);

            $this->query($filters)->latest('id')->chunk(500, function ($bookings) use ($out) {
                foreach ($bookings as $b) {
                    fputcsv($out, Csv::row([
                        $b->reference, $b->created_at?->format('Y-m-d H:i'), Booking::STATUSES[$b->status] ?? $b->status,
                        $b->snap('package', 'en'), trim($b->snap('type', 'en').' – '.$b->snap('option', 'en'), ' –'),
                        $b->travelers, TravelDates::label($b->travel_month, $b->travel_year, 'en'), $b->total, $b->advance_amount, $b->paid_amount, number_format($b->balance(), 2, '.', ''),
                        $b->customer_name, Csv::phone($b->customer_phone), $b->customer_email,
                    ]));
                }
            });

            fclose($out);
        }, 'bookings-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * Partially paid, advance paid and fully paid follow the payments; staff
     * cannot pick them, and while money has been paid Pending/Confirmed would
     * hide it. Completed and Cancelled are always theirs to choose; an
     * unchanged status always saves (notes, messages).
     */
    private function statusFollowsPayments(Booking $booking): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($booking) {
            if ($value === $booking->status || in_array($value, ['completed', 'cancelled'], true)) {
                return;
            }

            if (in_array($value, BookingService::PAYMENT_STATUSES, true)) {
                $fail('Partially paid, advance paid and fully paid follow the payments. Record a payment and the status changes by itself.');
            } elseif ((float) $booking->paid_amount > 0) {
                $fail('This booking has payments, so its status follows them. Remove the payments first, or choose Completed or Cancelled.');
            }
        };
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'status' => ['nullable', Rule::in(array_keys(Booking::STATUSES))],
            'package_id' => ['nullable', 'integer'],
            'payment' => ['nullable', Rule::in(['unpaid', 'partial', 'paid'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'total_desc', 'balance_desc'])],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function query(array $filters): Builder
    {
        return Booking::query()
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['package_id'] ?? null, fn ($q, $id) => $q->where('package_id', $id))
            ->when($filters['payment'] ?? null, fn ($q, $payment) => match ($payment) {
                'unpaid' => $q->where('paid_amount', '<=', 0),
                'partial' => $q->where('paid_amount', '>', 0)->whereColumn('paid_amount', '<', 'total'),
                default => $q->where('total', '>', 0)->whereColumn('paid_amount', '>=', 'total'),
            })
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $like = '%'.addcslashes($search, '%_').'%';
                $q->where(fn ($q) => $q->where('reference', 'like', $like)
                    ->orWhere('customer_name', 'like', $like)
                    ->orWhere('customer_phone', 'like', $like)
                    ->orWhere('customer_email', 'like', $like));
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'reference' => $booking->reference,
            'status' => $booking->status,
            'package' => $booking->snap('package', 'en'),
            'option' => trim($booking->snap('type', 'en').' – '.$booking->snap('option', 'en'), ' –'),
            'travelers' => $booking->travelers,
            'travel_date' => TravelDates::label($booking->travel_month, $booking->travel_year, 'en'),
            'total' => (float) $booking->total,
            'paid_amount' => (float) $booking->paid_amount,
            'balance' => $booking->balance(),
            'overpaid' => $booking->overpaid(),
            'customer_name' => $booking->customer_name,
            'customer_phone' => $booking->customer_phone,
            'customer_email' => $booking->customer_email,
            'package_photo' => $booking->relationLoaded('package') ? $booking->package?->banner?->url('admin') : null,
            'is_unread' => $booking->read_at === null,
            'created_at' => $booking->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Booking $booking): array
    {
        $booking->load(['payments.recorder:id,name', 'documents', 'package:id,slug,status', 'updates.creator:id,name']);

        return [
            ...$this->summary($booking),
            'package_id' => $booking->package_id,
            'package_url' => $booking->package?->isPublished() ? $booking->package->url() : null,
            'public_url' => $booking->publicUrl(),
            'unit' => $booking->snapshot['unit'] ?? 'person',
            'unit_price' => (float) $booking->unit_price,
            'quantity' => $booking->quantity,
            'advance_type' => $booking->advance_type,
            'advance_value' => $booking->advance_value !== null ? (float) $booking->advance_value : null,
            'advance_amount' => (float) $booking->advance_amount,
            'currency' => $booking->currency,
            'customer_email' => $booking->customer_email,
            'customer_address' => $booking->customer_address,
            'customer_note' => $booking->customer_note,
            'admin_notes' => $booking->admin_notes,
            'locale' => $booking->locale,
            'documents' => $booking->documents->map(fn (BookingDocument $d) => [
                'id' => $d->id,
                'kind' => $d->kind,
                'kind_label' => BookingDocument::KINDS[$d->kind] ?? $d->kind,
                'name' => $d->original_name,
                'size' => $d->size,
                'is_image' => str_starts_with($d->mime_type, 'image/'),
                'url' => "/api/admin/bookings/{$booking->id}/documents/{$d->id}",
            ]),
            'payments' => $booking->payments->map(fn (Payment $p) => [
                'id' => $p->id,
                'method' => $p->method,
                'method_label' => $p->methodLabel(),
                'is_online' => $p->is_online,
                'status' => $p->status,
                'amount' => (float) $p->amount,
                'transaction_id' => $p->transaction_id,
                'gateway_reference' => $p->gateway_reference,
                'reference' => $p->reference,
                'note' => $p->note,
                'recorded_by' => $p->recorder?->name,
                'paid_at' => $p->paid_at?->toIso8601String(),
                'created_at' => $p->created_at?->toIso8601String(),
                'log' => $p->log ?? [],
            ]),
            'offline_methods' => Payment::OFFLINE_METHODS,
            'activity' => $this->log->timeline($booking),
            'statuses' => Booking::STATUSES,
            'updates' => $booking->updates->map(fn (BookingUpdate $u) => [
                'id' => $u->id,
                'kind' => $u->kind,
                'status' => $u->status,
                'message' => $u->message,
                'amount' => $u->amount !== null ? (float) $u->amount : null,
                'notify' => (bool) $u->notify,
                'emailed' => $u->emailed_at !== null,
                'by' => $u->creator?->name,
                'created_at' => $u->created_at?->toIso8601String(),
            ]),
        ];
    }
}
