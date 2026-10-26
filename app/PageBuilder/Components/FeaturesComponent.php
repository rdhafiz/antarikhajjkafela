<?php

namespace App\PageBuilder\Components;

use App\PageBuilder\Component;
use App\PageBuilder\Field;

class FeaturesComponent extends Component
{
    use HasHeading;

    public function key(): string
    {
        return 'features';
    }

    public function name(): string
    {
        return 'Services / features';
    }

    public function description(): string
    {
        return 'A grid of services or reasons to choose you, each with an icon.';
    }

    public function icon(): string
    {
        return 'layout-grid';
    }

    public function fields(): array
    {
        return [
            ...$this->headingFields(),
            Field::select('layout', 'Style', ['cards' => 'Cards', 'photos' => 'Photo cards with the text over the image', 'sticky' => 'Split heading with a compact connected grid', 'list' => 'Icon list', 'numbered' => 'Numbered rows', 'tiles' => 'Numbered tiles that fill with colour on hover']),
            Field::select('columns', 'Columns', ['3' => '3', '2' => '2', '4' => '4']),
            Field::select('icon_style', 'Icon colours', ['brand' => 'Brand colour', 'colorful' => 'Colourful: a different colour for each item'])->default('brand'),
            Field::repeater('items', 'Items', [
                Field::icon('icon', 'Icon'),
                Field::image('image', 'Image', 'card')->hint('Optional. Shown at the top of cards, or behind the text on photo cards.'),
                Field::text('title', 'Title')->max(80)->required(),
                Field::textarea('text', 'Text')->max(400)->rows(2),
                Field::link('link', 'Link'),
            ])->max(12)->itemLabel('title'),
            Field::link('cta', 'Button below the grid'),
        ];
    }
}
