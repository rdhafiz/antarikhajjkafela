<?php

namespace App\PageBuilder\Components;

use App\Models\Page;
use App\Models\PageSection;
use App\Models\Review;
use App\PageBuilder\Component;
use App\PageBuilder\Field;

class TestimonialsComponent extends Component
{
    use HasHeading;

    public function key(): string
    {
        return 'testimonials';
    }

    public function name(): string
    {
        return 'Testimonials';
    }

    public function description(): string
    {
        return 'Pilgrim reviews from the Reviews module: featured ones or the newest.';
    }

    public function category(): string
    {
        return 'dynamic';
    }

    public function icon(): string
    {
        return 'message-square-quote';
    }

    public function fields(): array
    {
        return [
            ...$this->headingFields(),
            Field::select('source', 'Show', ['featured' => 'Featured reviews first', 'text' => 'Written reviews only', 'video' => 'Video reviews only']),
            Field::number('limit', 'How many', 1, 12)->default(3),
            Field::link('cta', 'Button below the reviews'),
        ];
    }

    public function data(array $content, PageSection $section, Page $page): array
    {
        $source = $content['source'] ?? 'featured';
        $locale = app()->getLocale();

        $reviews = Review::published()
            ->with('photo')
            ->when($source === 'video', fn ($q) => $q->whereNotNull('video_url'))
            ->ordered()
            ->get()
            ->filter(fn (Review $r) => $source !== 'text' || filled($r->getTranslation('text', $locale)))
            ->take(max(1, min(12, (int) ($content['limit'] ?? 3))))
            ->values();

        return ['reviews' => $reviews];
    }

    public function defaultStyle(): array
    {
        return ['background' => 'white', 'spacing' => 'lg'];
    }
}
