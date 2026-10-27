<?php

use App\Domain\Booking\BookingLog;
use App\Domain\Booking\BookingService;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Activitylog\Models\Activity;

/**
 * Bookings get their own activity log and a "Partially paid" status. Bookings
 * made before this start their log with the booking itself and the payments
 * already recorded, then their status is recalculated from what is paid
 * (logged when it changes, for example "Advance paid" → "Partially paid").
 */
return new class extends Migration
{
    public function up(): void
    {
        Booking::withTrashed()->with('payments')->orderBy('id')->each(function (Booking $booking) {
            if (Activity::query()->where('log_name', BookingLog::LOG)->whereMorphedTo('subject', $booking)->exists()) {
                return;
            }

            $entries = [['created', 'Booking placed', $booking->created_at, null, [
                'customer' => $booking->customer_name,
                'option' => trim($booking->snap('type', 'en').' – '.$booking->snap('option', 'en'), ' –'),
                'travelers' => $booking->travelers,
                'total' => (float) $booking->total,
            ]]];

            foreach ($booking->payments->sortBy('id') as $payment) {
                $event = $payment->is_online ? match ($payment->status) {
                    'paid' => 'payment_paid',
                    'initiated' => 'payment_started',
                    'cancelled' => 'payment_cancelled',
                    default => 'payment_failed',
                } : 'payment_recorded';

                $entries[] = [$event, BookingLog::EVENTS[$event][1], $payment->paid_at ?? $payment->created_at, $payment->recorded_by, [
                    'amount' => (float) $payment->amount,
                    'method' => $payment->methodLabel(),
                    'reference' => $payment->reference ?? $payment->gateway_reference,
                ]];
            }

            foreach ($entries as [$event, $label, $at, $by, $properties]) {
                Activity::query()->create([
                    'log_name' => BookingLog::LOG,
                    'description' => $label,
                    'event' => $event,
                    'subject_type' => $booking->getMorphClass(),
                    'subject_id' => $booking->id,
                    'causer_type' => $by ? (new User)->getMorphClass() : null,
                    'causer_id' => $by,
                    'properties' => $properties,
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);
            }

            if (! $booking->trashed()) {
                app(BookingService::class)->refreshPayments($booking);
            }
        });
    }

    public function down(): void
    {
        // The logs stay.
    }
};
