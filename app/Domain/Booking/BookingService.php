<?php

namespace App\Domain\Booking;

use App\Mail\BookingPlaced;
use App\Mail\BookingReceived;
use App\Mail\BookingUpdated;
use App\Mail\PaymentReceived;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\BookingUpdate;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Creates bookings, keeps their paid amount and status in step with the
 * payment ledger, records the customer-facing history and sends the booking
 * emails (there are no customer accounts, so email is how customers hear of
 * every change).
 */
class BookingService
{
    public function __construct(private readonly BookingCalculator $calculator) {}

    /**
     * @param  array{travelers: int, rooms?: int|null, name: string, phone: string, email?: string|null, address?: string|null, note?: string|null}  $data
     * @param  array<int, array{kind: string, file: UploadedFile}>  $documents
     */
    public function create(Package $package, PackagePrice $price, array $data, array $documents = [], ?string $ip = null): Booking
    {
        $price->loadMissing('type');
        $amounts = $this->calculator->calculate($package, $price, $data['travelers'], $data['rooms'] ?? null);
        $stored = [];

        try {
            $booking = DB::transaction(function () use ($package, $price, $data, $amounts, $documents, $ip, &$stored) {
                $booking = Booking::create([
                    'reference' => 'TMP-'.Str::random(16),
                    'package_id' => $package->id,
                    'package_price_id' => $price->id,
                    'snapshot' => [
                        'package' => $package->getTranslations('name'),
                        'package_slug' => $package->slug,
                        'service' => $package->service?->getTranslations('name') ?? [],
                        'type' => $price->type?->getTranslations('name') ?? [],
                        'type_code' => $price->type?->code,
                        'option' => $price->getTranslations('title'),
                        'unit' => $price->unit,
                        'duration_days' => $package->duration_days,
                        // The customer saw no prices: their pages and emails leave the amounts out.
                        'price_hidden' => ! $package->show_price,
                    ],
                    ...$amounts,
                    'travelers' => $data['travelers'],
                    'travel_month' => $data['travel_month'] ?? null,
                    'travel_year' => $data['travel_year'] ?? null,
                    'currency' => settings('localization.currency') ?: 'BDT',
                    'status' => 'pending',
                    'customer_name' => $data['name'],
                    'customer_phone' => $data['phone'],
                    'customer_email' => $data['email'] ?? null,
                    'customer_address' => $data['address'] ?? null,
                    'customer_note' => $data['note'] ?? null,
                    'locale' => app()->getLocale(),
                    'ip' => $ip,
                ]);

                // Reference from the row id, e.g. AHK-2026-000123.
                $booking->forceFill(['reference' => sprintf('AHK-%s-%06d', now()->format('Y'), $booking->id)])->save();

                $booking->updates()->create(['kind' => 'created', 'status' => 'pending']);

                foreach ($documents as $document) {
                    $path = $document['file']->store("bookings/{$booking->id}", BookingDocument::DISK);
                    $stored[] = $path;

                    $booking->documents()->create([
                        'kind' => $document['kind'],
                        'path' => $path,
                        'original_name' => Str::limit($document['file']->getClientOriginalName(), 180, ''),
                        'mime_type' => (string) $document['file']->getMimeType(),
                        'size' => (int) $document['file']->getSize(),
                    ]);
                }

                return $booking;
            });
        } catch (Throwable $e) {
            foreach ($stored as $path) {
                Storage::disk(BookingDocument::DISK)->delete($path);
            }

            throw $e;
        }

        if ($this->sendBookingEmails($booking)) {
            $booking->updates()->where('kind', 'created')->update(['emailed_at' => now()]);
        }

        return $booking;
    }

    /**
     * Staff changed the booking: record it in the customer's history and, when
     * asked, email the customer the latest details.
     *
     * @param  'status'|'message'  $kind
     */
    public function recordUpdate(Booking $booking, string $kind, ?string $message = null, bool $notify = true, ?User $by = null): BookingUpdate
    {
        $update = $booking->updates()->create([
            'kind' => $kind,
            'status' => $booking->status,
            'message' => $message,
            'created_by' => $by?->id,
        ]);

        if ($notify && $booking->customer_email) {
            $sent = $this->safely(fn () => Mail::to($booking->customer_email)->locale($booking->locale)->send(new BookingUpdated($booking->fresh(), $update)));

            if ($sent) {
                $update->update(['emailed_at' => now()]);
            }
        }

        return $update;
    }

    /**
     * A payment was received (online or at the office): record it and send
     * the customer a receipt with the new balance.
     */
    public function recordPayment(Payment $payment, bool $notify = true, ?User $by = null, ?string $message = null): BookingUpdate
    {
        $booking = $payment->booking->fresh();

        $update = $booking->updates()->create([
            'kind' => 'payment',
            'status' => $booking->status,
            'amount' => $payment->amount,
            'message' => $message,
            'created_by' => $by?->id,
        ]);

        if ($notify && $this->sendPaymentEmail($payment, $update)) {
            $update->update(['emailed_at' => now()]);
        }

        return $update;
    }

    /**
     * Recalculate the paid amount and move the status forward when payments
     * cover the advance or the total. Closed bookings keep their status.
     */
    public function refreshPayments(Booking $booking): Booking
    {
        $paid = round((float) $booking->payments()->where('status', 'paid')->sum('amount'), 2);
        $booking->paid_amount = $paid;

        // The status follows the money both ways. Removing a payment used to
        // leave the old one behind, so a booking could read "Fully paid" with
        // nothing recorded against it. Completed and cancelled are left alone,
        // and so is a booking still in the workflow whose payments justify no
        // payment status — pending and confirmed are not ours to overwrite.
        if (! $booking->isClosed()) {
            $earned = match (true) {
                $paid >= (float) $booking->total && (float) $booking->total > 0 => 'fully_paid',
                $paid > 0 && $paid >= (float) $booking->advance_amount => 'advance_paid',
                default => null,
            };

            if ($earned !== null) {
                $booking->status = $earned;
            } elseif (in_array($booking->status, ['advance_paid', 'fully_paid'], true)) {
                $booking->status = 'pending';
            }
        }

        $booking->save();

        return $booking;
    }

    /**
     * Mark an online payment as paid exactly once, however many times the
     * gateway calls back.
     *
     * @param  array<string, mixed>  $details
     */
    public function markPaid(Payment $payment, array $details = []): bool
    {
        $changed = DB::transaction(function () use ($payment, $details) {
            $fresh = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if ($fresh->isPaid()) {
                return false;
            }

            $fresh->fill([
                'status' => 'paid',
                'paid_at' => now(),
                'gateway_reference' => $details['gateway_reference'] ?? $fresh->gateway_reference,
            ])->save();

            $this->refreshPayments($fresh->booking);

            return true;
        });

        if ($changed) {
            $payment->refresh();
            $this->recordPayment($payment);
        }

        return $changed;
    }

    public function markFailed(Payment $payment, string $status = 'failed'): void
    {
        DB::transaction(function () use ($payment, $status) {
            $fresh = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if ($fresh->status === 'initiated') {
                $fresh->update(['status' => $status]);
            }
        });

        $payment->refresh();
    }

    /**
     * @return bool whether the customer's email went out
     */
    private function sendBookingEmails(Booking $booking): bool
    {
        $sent = $booking->customer_email
            && $this->safely(fn () => Mail::to($booking->customer_email)->locale($booking->locale)->send(new BookingReceived($booking)));

        $this->safely(function () use ($booking) {
            if ($to = settings('contact.primary_email')) {
                Mail::to($to)->locale('en')->send(new BookingPlaced($booking));
            }
        });

        return $sent;
    }

    private function sendPaymentEmail(Payment $payment, ?BookingUpdate $update = null): bool
    {
        $booking = $payment->booking->fresh();

        return $booking->customer_email
            && $this->safely(fn () => Mail::to($booking->customer_email)->locale($booking->locale)->send(new PaymentReceived($booking, $payment, $update)));
    }

    /**
     * The booking is saved; a mail problem must not fail the request.
     *
     * @return bool whether it went through
     */
    private function safely(callable $send): bool
    {
        try {
            $send();

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
