<?php

namespace App\PageBuilder\Components;

use App\PageBuilder\Component;
use App\PageBuilder\Field;

class QuoteComponent extends Component
{
    public function key(): string
    {
        return 'quote';
    }

    public function name(): string
    {
        return 'Verse / quote';
    }

    public function description(): string
    {
        return 'A Quran verse, hadith or meaningful quote, presented prominently.';
    }

    public function icon(): string
    {
        return 'quote';
    }

    public function fields(): array
    {
        return [
            Field::textarea('arabic', 'Arabic text')->notTranslatable()->max(1000)->rows(2)->hint('Optional. Shown right-to-left above the translation.'),
            Field::textarea('text', 'Text')->max(1000)->rows(3)->required(),
            Field::text('source', 'Source')->max(120)->placeholder('Surah Al-Hajj 22:27'),
        ];
    }

    public function defaultStyle(): array
    {
        return ['background' => 'navy', 'spacing' => 'md'];
    }
}
