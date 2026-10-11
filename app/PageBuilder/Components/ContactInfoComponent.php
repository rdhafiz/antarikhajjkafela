<?php

namespace App\PageBuilder\Components;

use App\PageBuilder\Component;
use App\PageBuilder\Field;

class ContactInfoComponent extends Component
{
    use HasHeading;

    public function key(): string
    {
        return 'contact_info';
    }

    public function name(): string
    {
        return 'Contact details & map';
    }

    public function description(): string
    {
        return 'Address, phones, emails and WhatsApp from Settings, with the office map.';
    }

    public function category(): string
    {
        return 'conversion';
    }

    public function icon(): string
    {
        return 'map-pinned';
    }

    public function fields(): array
    {
        return [
            ...$this->headingFields(),
            Field::textarea('hours', 'Office hours')->max(300)->rows(2)->placeholder('Saturday – Thursday, 10am – 7pm'),
            Field::toggle('show_map', 'Show the map')->default(true),
        ];
    }

    public function defaultStyle(): array
    {
        return ['background' => 'white', 'spacing' => 'lg'];
    }
}
