<?php

namespace App\PageBuilder\Components;

use App\PageBuilder\Component;
use App\PageBuilder\Field;

class ImageTextComponent extends Component
{
    use HasHeading;

    public function key(): string
    {
        return 'image_text';
    }

    public function name(): string
    {
        return 'Image & text';
    }

    public function description(): string
    {
        return 'Text beside an image, with an optional checklist and buttons.';
    }

    public function icon(): string
    {
        return 'columns-2';
    }

    public function fields(): array
    {
        return [
            ...$this->headingFields(intro: false),
            Field::richtext('body', 'Text'),
            Field::repeater('bullets', 'Checklist', [
                Field::text('text', 'Item')->max(160)->required(),
            ])->max(10)->itemLabel('text'),
            Field::image('image', 'Image', 'content'),
            Field::select('image_position', 'Image position', ['right' => 'Right', 'left' => 'Left']),
            Field::text('badge', 'Image badge')->max(40)->hint('Short label over the image, e.g. "Since 2016".'),
            Field::link('primary', 'Primary button'),
            Field::link('secondary', 'Secondary button'),
        ];
    }
}
