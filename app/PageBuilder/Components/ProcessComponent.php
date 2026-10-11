<?php

namespace App\PageBuilder\Components;

use App\PageBuilder\Component;
use App\PageBuilder\Field;

class ProcessComponent extends Component
{
    use HasHeading;

    public function key(): string
    {
        return 'process_steps';
    }

    public function name(): string
    {
        return 'Process steps';
    }

    public function description(): string
    {
        return 'Numbered steps such as how to book or how to prepare for Hajj.';
    }

    public function icon(): string
    {
        return 'list-ordered';
    }

    public function fields(): array
    {
        return [
            ...$this->headingFields(),
            Field::repeater('steps', 'Steps', [
                Field::icon('icon', 'Icon'),
                Field::text('title', 'Title')->max(80)->required(),
                Field::textarea('text', 'Text')->max(400)->rows(2),
            ])->min(1)->max(8)->itemLabel('title'),
        ];
    }

    public function defaultStyle(): array
    {
        return ['background' => 'white', 'spacing' => 'lg'];
    }
}
