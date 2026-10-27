<?php

namespace App\Domain\Booking;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;
use Throwable;

/**
 * Each booking's own activity log (activity_log, log name "booking"): what
 * staff, the customer, the payment gateway and the mailer did to it, shown on
 * the admin booking page. Writing a log entry must never fail the request.
 */
class BookingLog
{
    public const LOG = 'booking';

    /**
     * Event => [category, label] for the admin timeline.
     */
    public const EVENTS = [
        'created' => ['customer', 'Booking placed'],
        'status_changed' => ['staff', 'Status changed'],
        'status_auto' => ['payment', 'Status followed the payments'],
        'notes_changed' => ['staff', 'Internal notes edited'],
        'message_sent' => ['staff', 'Message to the customer'],
        'payment_recorded' => ['payment', 'Office payment recorded'],
        'payment_removed' => ['payment', 'Office payment removed'],
        'payment_started' => ['payment', 'Online payment started'],
        'payment_paid' => ['payment', 'Online payment received'],
        'payment_failed' => ['payment', 'Online payment failed'],
        'payment_cancelled' => ['payment', 'Online payment cancelled'],
        'payment_withdrawn' => ['payment', 'Unfinished online payment cancelled'],
        'overpaid' => ['payment', 'Overpaid: refund needed'],
        'email_sent' => ['email', 'Email sent'],
        'email_failed' => ['email', 'Email failed'],
        'customer_viewed' => ['customer', 'Customer opened their booking page'],
        'customer_lookup' => ['customer', 'Customer found the booking with Find your booking'],
        'document_opened' => ['staff', 'Document opened'],
    ];

    /**
     * @param  array<string, mixed>  $properties
     * @param  User|false|null  $by  a staff member, false for "the customer or the system", null for whoever is signed in
     */
    public function record(Booking $booking, string $event, array $properties = [], User|false|null $by = false): void
    {
        try {
            $logger = activity(self::LOG)->performedOn($booking)->event($event)->withProperties($properties);
            $by === false ? $logger->causedByAnonymous() : $logger->causedBy($by);
            $logger->log(self::EVENTS[$event][1] ?? $event);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Whether the event was logged for this booking within the last minutes (to keep page views from flooding the log).
     */
    public function recently(Booking $booking, string $event, int $minutes): bool
    {
        return Activity::query()->where('log_name', self::LOG)->whereMorphedTo('subject', $booking)
            ->where('event', $event)->where('created_at', '>=', now()->subMinutes($minutes))->exists();
    }

    /**
     * The booking's log for the admin, newest first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function timeline(Booking $booking): Collection
    {
        return Activity::query()->where('log_name', self::LOG)->whereMorphedTo('subject', $booking)
            ->with('causer:id,name')->latest('id')->limit(500)->get()
            ->map(fn (Activity $a) => [
                'id' => $a->id,
                'event' => $a->event,
                'category' => self::EVENTS[$a->event][0] ?? 'staff',
                'label' => $a->description,
                'by' => $a->causer?->name,
                'details' => $a->properties?->toArray() ?? [],
                'at' => $a->created_at?->toIso8601String(),
            ]);
    }
}
