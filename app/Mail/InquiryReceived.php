<?php

namespace App\Mail;

use App\Models\Inquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InquiryReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Inquiry $inquiry) {}

    public function envelope(): Envelope
    {
        $source = $this->inquiry->source === 'contact_form' ? 'Website message' : $this->inquiry->sourceLabel();

        return new Envelope(
            subject: "{$source}: ".($this->inquiry->name ?: $this->inquiry->phone ?: 'New enquiry'),
            replyTo: $this->inquiry->email ? [new Address($this->inquiry->email, (string) $this->inquiry->name)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.inquiry-received');
    }
}
