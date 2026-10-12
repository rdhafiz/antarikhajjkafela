<?php

namespace App\PageBuilder\Components;

use App\PageBuilder\Component;
use App\PageBuilder\Field;

class CtaComponent extends Component
{
    public function key(): string
    {
        return 'cta';
    }

    public function name(): string
    {
        return 'Call to action';
    }

    public function description(): string
    {
        return 'A bold invitation to call, chat on WhatsApp or book.';
    }

    public function category(): string
    {
        return 'conversion';
    }

    public function icon(): string
    {
        return 'megaphone';
    }

    public function fields(): array
    {
        return [
            Field::select('variant', 'Style', ['banner' => 'Banner card', 'split' => 'With image', 'minimal' => 'Minimal']),
            Field::text('eyebrow', 'Eyebrow')->max(60),
            Field::text('title', 'Title')->max(160)->required(),
            Field::textarea('text', 'Text')->max(400)->rows(2),
            Field::link('primary', 'Primary button'),
            Field::link('secondary', 'Secondary button'),
            Field::toggle('contact_buttons', 'Show call & WhatsApp buttons')->default(true)->hint('Uses the phone and WhatsApp numbers from Settings.'),
            Field::image('image', 'Image', 'banner')->showIf('variant', ['split', 'banner'])->hint('Banner: a background photo that moves gently while scrolling.'),
        ];
    }

    public function defaultStyle(): array
    {
        return ['background' => 'sand', 'spacing' => 'md'];
    }
}
