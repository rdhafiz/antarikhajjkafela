<?php

namespace App\PageBuilder\Components;

use App\PageBuilder\Component;
use App\PageBuilder\Field;

class AboutComponent extends Component
{
    use HasHeading;

    public function key(): string
    {
        return 'about';
    }

    public function name(): string
    {
        return 'About';
    }

    public function description(): string
    {
        return 'Introduce the agency with an image collage, years of experience and a quote.';
    }

    public function icon(): string
    {
        return 'info';
    }

    public function fields(): array
    {
        return [
            ...$this->headingFields(intro: false),
            Field::richtext('body', 'Text'),
            Field::image('image', 'Main image', 'content'),
            Field::image('image_secondary', 'Second image', 'card'),
            Field::text('years_value', 'Experience figure')->notTranslatable()->max(8)->placeholder('9+'),
            Field::text('years_label', 'Experience label')->max(40)->placeholder('Years of service'),
            Field::textarea('quote', 'Quote')->max(400)->rows(3),
            Field::text('quote_author', 'Quote author')->max(80),
            Field::link('link', 'Button'),
        ];
    }
}
