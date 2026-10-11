<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the customer when an online payment is confirmed.
 */
class PaymentReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking, public Payment $payment) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Payment received for booking :reference', ['reference' => $this->booking->reference]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.payment-received');
    }
}
