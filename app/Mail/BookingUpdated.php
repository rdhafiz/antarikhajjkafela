<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\BookingUpdate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the customer when staff change the booking's status or send a
 * message, with the booking's latest details.
 */
class BookingUpdated extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking, public BookingUpdate $update) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->update->kind === 'status'
            ? __('Booking :reference: :status', ['reference' => $this->booking->reference, 'status' => Booking::statusLabel($this->booking->status)])
            : __('Update on your booking :reference', ['reference' => $this->booking->reference]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.booking-updated');
    }
}
