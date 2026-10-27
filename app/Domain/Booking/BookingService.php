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
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Creates bookings, keeps their paid amount and status in step with the
 * payment ledger, records the customer-facing history and sends the booking
 * emails (there are no customer accounts, so email is how customers hear of
 * every change).
 */
class BookingService
{
    public function __construct(
        private readonly BookingCalculator $calculator,
        private readonly BookingLog $log,
    ) {}

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

                $booking->updates()->create(['kind' => 'created', 'status' => 'pending', 'notify' => filled($data['email'] ?? null)]);

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

        $this->log->record($booking, 'created', [
            'customer' => $booking->customer_name,
            'option' => trim($booking->snap('type', 'en').' – '.$booking->snap('option', 'en'), ' –'),
            'travelers' => $booking->travelers,
            'total' => (float) $booking->total,
            'documents' => count($documents),
            'ip' => $ip,
        ]);

        $this->sendBookingEmails($booking);

        return $booking;
    }

    /**
     * Cancel online payments a customer started but never finished, once the
     * balance is settled another way (an office payment). A gateway that still
     * confirms one later is recorded and flagged as overpaid.
     */
    public function withdrawUnfinishedPayments(Booking $booking, ?User $by = null): void
    {
        $booking->payments()->where('is_online', true)->where('status', 'initiated')->get()->each(function (Payment $payment) use ($booking, $by) {
            $payment->update(['status' => 'cancelled']);
            $payment->addLog('withdrawn', ['reason' => 'An office payment was recorded for this booking.']);
            $this->log->record($booking, 'payment_withdrawn', ['amount' => (float) $payment->amount, 'transaction' => $payment->transaction_id], $by ?? null);
        });
    }

    /**
     * Staff changed the booking: record it in the customer's history and, when
     * asked, email the customer the latest details.
     *
     * @param  'status'|'message'  $kind
     */
    public function recordUpdate(Booking $booking, string $kind, ?string $message = null, bool $notify = true, ?User $by = null): BookingUpdate
    {
        $notify = $notify && filled($booking->customer_email);

        $update = $booking->updates()->create([
            'kind' => $kind,
            'status' => $booking->status,
            'message' => $message,
            'notify' => $notify,
            'created_by' => $by?->id,
        ]);

        if ($notify) {
            $this->sendMail($booking, $booking->customer_email, $booking->locale, new BookingUpdated($booking->fresh(), $update), 'Booking update', $update);
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

        $notify = $notify && filled($booking->customer_email);

        $update = $booking->updates()->create([
            'kind' => 'payment',
            'status' => $booking->status,
            'amount' => $payment->amount,
            'message' => $message,
            'notify' => $notify,
            'created_by' => $by?->id,
        ]);

        if ($notify) {
            $this->sendMail($booking, $booking->customer_email, $booking->locale, new PaymentReceived($booking, $payment, $update), 'Payment receipt', $update);
        }

        return $update;
    }

    /**
     * Statuses that follow the money; the others are set by staff.
     */
    public const PAYMENT_STATUSES = ['partially_paid', 'advance_paid', 'fully_paid'];

    /**
     * Recalculate the paid amount and let the status follow it, both ways:
     * nothing paid → the status staff gave it before any payment (pending or
     * confirmed); less than the advance (or any amount when there is no
     * advance) → partially paid; the advance → advance paid; the total →
     * fully paid. Completed and cancelled bookings keep their status.
     */
    public function refreshPayments(Booking $booking): Booking
    {
        $before = ['status' => $booking->getOriginal('status') ?? $booking->status, 'overpaid' => $booking->overpaid()];
        $paid = round((float) $booking->payments()->where('status', 'paid')->sum('amount'), 2);
        $booking->paid_amount = $paid;

        if (! $booking->isClosed()) {
            $total = (float) $booking->total;
            $advance = (float) $booking->advance_amount;

            $earned = match (true) {
                $total > 0 && $paid >= $total => 'fully_paid',
                $advance > 0 && $paid >= $advance => 'advance_paid',
                $paid > 0 => 'partially_paid',
                default => null,
            };

            if ($earned !== null) {
                $booking->status = $earned;
            } elseif (in_array($booking->status, self::PAYMENT_STATUSES, true)) {
                $booking->status = $this->statusBeforePayments($booking);
            }
        }

        $booking->save();

        if ($booking->status !== $before['status']) {
            $this->log->record($booking, 'status_auto', ['from' => $before['status'], 'to' => $booking->status, 'paid' => $paid]);
        }

        // Paid above the total: only a gateway confirming money no longer due gets here.
        if ($booking->overpaid() > 0 && $booking->overpaid() > $before['overpaid']) {
            $this->log->record($booking, 'overpaid', ['amount' => $booking->overpaid(), 'paid' => $paid, 'total' => (float) $booking->total]);
        }

        return $booking;
    }

    /**
     * The status staff last gave the booking that does not depend on payments (pending or confirmed).
     */
    private function statusBeforePayments(Booking $booking): string
    {
        return $booking->updates()->whereIn('kind', ['status', 'created'])->whereIn('status', ['pending', 'confirmed'])->value('status') ?? 'pending';
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

            $this->log->record($fresh->booking, 'payment_paid', [
                'amount' => (float) $fresh->amount,
                'gateway' => $fresh->methodLabel(),
                'transaction' => $fresh->transaction_id,
                'gateway_reference' => $fresh->gateway_reference,
            ]);

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
                $this->log->record($fresh->booking, $status === 'cancelled' ? 'payment_cancelled' : 'payment_failed', [
                    'amount' => (float) $fresh->amount,
                    'gateway' => $fresh->methodLabel(),
                    'transaction' => $fresh->transaction_id,
                ]);
            }
        });

        $payment->refresh();
    }

    private function sendBookingEmails(Booking $booking): void
    {
        if ($booking->customer_email) {
            $this->sendMail($booking, $booking->customer_email, $booking->locale, new BookingReceived($booking), 'Booking received', $booking->updates()->where('kind', 'created')->first());
        }

        if ($to = settings('contact.primary_email')) {
            $this->sendMail($booking, $to, 'en', new BookingPlaced($booking), 'New booking (office)');
        }
    }

    /**
     * Send one email after the response has gone to the browser (an SMTP send
     * takes seconds), then log it on the booking and mark the customer update
     * as emailed. A mail problem never fails anything: it is reported and logged.
     */
    private function sendMail(Booking $booking, string $to, ?string $locale, Mailable $mail, string $label, ?BookingUpdate $update = null): void
    {
        defer(function () use ($booking, $to, $locale, $mail, $label, $update) {
            try {
                Mail::to($to)->locale($locale ?: 'en')->send($mail);
                $update?->forceFill(['emailed_at' => now()])->save();
                $this->log->record($booking, 'email_sent', ['email' => $label, 'to' => $to]);
            } catch (Throwable $e) {
                report($e);
                $this->log->record($booking, 'email_failed', ['email' => $label, 'to' => $to, 'error' => Str::limit($e->getMessage(), 300)]);
            }
        }, always: true);
    }
}
