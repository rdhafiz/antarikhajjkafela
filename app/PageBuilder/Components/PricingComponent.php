<?php

namespace App\PageBuilder\Components;

use App\PageBuilder\Component;
use App\PageBuilder\Field;

class PricingComponent extends Component
{
    use HasHeading;

    public function key(): string
    {
        return 'pricing';
    }

    public function name(): string
    {
        return 'Price cards';
    }

    public function description(): string
    {
        return 'Hand-written price cards for offers and campaigns (packages have their own pages).';
    }

    public function category(): string
    {
        return 'conversion';
    }

    public function icon(): string
    {
        return 'badge-dollar-sign';
    }

    public function fields(): array
    {
        return [
            ...$this->headingFields(),
            Field::repeater('plans', 'Cards', [
                Field::text('name', 'Name')->max(80)->required(),
                Field::text('badge', 'Badge')->max(30)->placeholder('Most popular'),
                Field::text('price', 'Price')->notTranslatable()->max(30)->placeholder('৳1,60,000'),
                Field::text('note', 'Price note')->max(60)->placeholder('per person'),
                Field::textarea('features', 'Included')->max(1500)->rows(5)->hint('One item per line.'),
                Field::link('link', 'Button'),
                Field::toggle('featured', 'Highlight this card'),
            ])->min(1)->max(4)->itemLabel('name'),
            Field::textarea('footnote', 'Footnote')->max(300)->rows(2),
        ];
    }
}
