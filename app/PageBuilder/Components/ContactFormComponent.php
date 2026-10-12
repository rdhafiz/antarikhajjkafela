<?php

namespace App\PageBuilder\Components;

use App\PageBuilder\Component;
use App\PageBuilder\Field;

class ContactFormComponent extends Component
{
    use HasHeading;

    public function key(): string
    {
        return 'contact_form';
    }

    public function name(): string
    {
        return 'Contact form';
    }

    public function description(): string
    {
        return 'Name, phone, email, subject and message. Messages arrive in the Inquiries inbox.';
    }

    public function category(): string
    {
        return 'conversion';
    }

    public function icon(): string
    {
        return 'mail';
    }

    public function fields(): array
    {
        return [
            ...$this->headingFields(),
            Field::select('layout', 'Style', ['split' => 'Heading beside the form', 'concierge' => 'Contact panel beside the form (phone, WhatsApp, email, office and a live Dhaka clock)'])->default('split'),
            Field::text('submit_label', 'Button text')->max(40)->placeholder('Send message'),
            Field::textarea('success', 'Thank-you message')->max(300)->rows(2)->placeholder('Thank you! We will contact you shortly.'),
        ];
    }

    public function defaultStyle(): array
    {
        return ['background' => 'sand', 'spacing' => 'lg'];
    }
}
