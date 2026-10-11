<?php

namespace App\PageBuilder\Components;

use App\PageBuilder\Component;
use App\PageBuilder\Field;

class LogosComponent extends Component
{
    use HasHeading;

    public function key(): string
    {
        return 'logos';
    }

    public function name(): string
    {
        return 'Logos';
    }

    public function description(): string
    {
        return 'Airlines, partners or accreditations shown as a row of logos.';
    }

    public function category(): string
    {
        return 'media';
    }

    public function icon(): string
    {
        return 'award';
    }

    public function fields(): array
    {
        return [
            ...$this->headingFields(intro: false),
            Field::repeater('logos', 'Logos', [
                Field::image('image', 'Logo', 'logo')->required(),
                Field::text('name', 'Name')->notTranslatable()->max(80)->required(),
            ])->max(16)->itemLabel('name'),
            Field::toggle('grayscale', 'Show in grayscale until hovered')->default(true),
        ];
    }

    public function defaultStyle(): array
    {
        return ['background' => 'white', 'spacing' => 'md'];
    }
}
