<?php

namespace App\Domain\Booking;

use App\Mail\BookingPlaced;
use App\Mail\BookingReceived;
use App\Mail\PaymentReceived;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\Payment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Creates bookings, keeps their paid amount and status in step with the
 * payment ledger, and sends the booking emails.
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

        $this->sendBookingEmails($booking);

        return $booking;
    }

    /**
     * Recalculate the paid amount and move the status forward when payments
     * cover the advance or the total. Closed bookings keep their status.
     */
    public function refreshPayments(Booking $booking): Booking
    {
        $paid = round((float) $booking->payments()->where('status', 'paid')->sum('amount'), 2);
        $booking->paid_amount = $paid;

        if (! $booking->isClosed()) {
            if ($paid >= (float) $booking->total && (float) $booking->total > 0) {
                $booking->status = 'fully_paid';
            } elseif ($paid > 0 && $paid >= (float) $booking->advance_amount && in_array($booking->status, ['pending', 'confirmed'], true)) {
                $booking->status = 'advance_paid';
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
            $this->sendPaymentEmail($payment);
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

    private function sendBookingEmails(Booking $booking): void
    {
        $this->safely(function () use ($booking) {
            if ($booking->customer_email) {
                Mail::to($booking->customer_email)->locale($booking->locale)->send(new BookingReceived($booking));
            }
        });

        $this->safely(function () use ($booking) {
            if ($to = settings('contact.primary_email')) {
                Mail::to($to)->locale('en')->send(new BookingPlaced($booking));
            }
        });
    }

    private function sendPaymentEmail(Payment $payment): void
    {
        $booking = $payment->booking;

        $this->safely(function () use ($booking, $payment) {
            if ($booking->customer_email) {
                Mail::to($booking->customer_email)->locale($booking->locale)->send(new PaymentReceived($booking, $payment));
            }
        });
    }

    /**
     * The booking is saved; a mail problem must not fail the visitor's request.
     */
    private function safely(callable $send): void
    {
        try {
            $send();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
