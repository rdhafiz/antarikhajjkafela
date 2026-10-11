<?php

namespace App\PageBuilder\Components;

use App\PageBuilder\Component;
use App\PageBuilder\Field;

class StatsComponent extends Component
{
    use HasHeading;

    public function key(): string
    {
        return 'stats';
    }

    public function name(): string
    {
        return 'Numbers';
    }

    public function description(): string
    {
        return 'Key figures such as pilgrims served or years of experience, counted up on scroll.';
    }

    public function icon(): string
    {
        return 'chart-no-axes-column';
    }

    public function fields(): array
    {
        return [
            ...$this->headingFields(),
            Field::repeater('items', 'Figures', [
                Field::icon('icon', 'Icon'),
                Field::number('value', 'Number', 0, 100000000)->required(),
                Field::text('suffix', 'Suffix')->notTranslatable()->max(4)->placeholder('+'),
                Field::text('label', 'Label')->max(50)->required(),
            ])->min(1)->max(6)->itemLabel('label'),
        ];
    }

    public function defaultStyle(): array
    {
        return ['background' => 'navy', 'spacing' => 'md'];
    }
}
