<?php

namespace App\PageBuilder\Components;

use App\Models\Page;
use App\Models\PageSection;
use App\PageBuilder\Component;
use App\PageBuilder\Field;
use Illuminate\Support\Carbon;

class CountdownComponent extends Component
{
    public function key(): string
    {
        return 'countdown';
    }

    public function name(): string
    {
        return 'Countdown';
    }

    public function description(): string
    {
        return 'Days, hours and minutes until a deadline, such as a registration closing date.';
    }

    public function category(): string
    {
        return 'conversion';
    }

    public function icon(): string
    {
        return 'timer';
    }

    public function fields(): array
    {
        return [
            Field::text('eyebrow', 'Eyebrow')->max(60),
            Field::text('title', 'Title')->max(160),
            Field::datetime('target', 'Count down to')->hint('Leave empty on a campaign page to use its expiry date.'),
            Field::text('expired_text', 'Text after the deadline')->max(160),
            Field::link('link', 'Button'),
        ];
    }

    public function data(array $content, PageSection $section, Page $page): array
    {
        $target = $content['target'] ? Carbon::parse($content['target']) : $page->expires_at;

        return ['target' => $target];
    }

    public function defaultStyle(): array
    {
        return ['background' => 'navy', 'spacing' => 'md'];
    }
}
