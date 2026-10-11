<?php

namespace App\PageBuilder\Components;

use App\PageBuilder\Component;
use App\PageBuilder\Field;

class RichTextComponent extends Component
{
    use HasHeading;

    public function key(): string
    {
        return 'rich_text';
    }

    public function name(): string
    {
        return 'Text';
    }

    public function description(): string
    {
        return 'Formatted text with headings, lists, links and tables. Ideal for policies and articles.';
    }

    public function icon(): string
    {
        return 'text';
    }

    public function fields(): array
    {
        return [
            ...$this->headingFields(intro: false),
            Field::richtext('body', 'Text')->required(),
            Field::select('width', 'Width', ['narrow' => 'Narrow (best for reading)', 'wide' => 'Wide']),
        ];
    }

    public function defaults(): array
    {
        return [
            ...parent::defaults(),
            'body' => ['en' => '<p>Start writing your content here.</p>'],
        ];
    }

    public function defaultStyle(): array
    {
        return ['background' => 'white', 'spacing' => 'md'];
    }
}
