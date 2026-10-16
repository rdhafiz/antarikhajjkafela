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
            Field::richtext('body', 'Text')->required()->hint('Start writing your content here.'),
            Field::select('width', 'Width', ['narrow' => 'Narrow (best for reading)', 'wide' => 'Wide']),
        ];
    }

    public function defaults(): array
    {
        // No seeded body: text left in one language only would publish as if
        // it were content, and the empty-locale fallback would never kick in.
        return parent::defaults();
    }

    public function defaultStyle(): array
    {
        return ['background' => 'white', 'spacing' => 'md'];
    }
}
