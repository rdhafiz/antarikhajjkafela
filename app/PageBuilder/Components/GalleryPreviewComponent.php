<?php

namespace App\PageBuilder\Components;

use App\Models\GalleryItem;
use App\Models\Page;
use App\Models\PageSection;
use App\PageBuilder\Component;
use App\PageBuilder\Field;

class GalleryPreviewComponent extends Component
{
    use HasHeading;

    public function key(): string
    {
        return 'gallery_preview';
    }

    public function name(): string
    {
        return 'Gallery preview';
    }

    public function description(): string
    {
        return 'A mosaic of the newest photos from published gallery albums.';
    }

    public function category(): string
    {
        return 'dynamic';
    }

    public function icon(): string
    {
        return 'images';
    }

    public function fields(): array
    {
        return [
            ...$this->headingFields(),
            Field::select('limit', 'How many photos', ['5' => '5', '8' => '8', '10' => '10'])->default('5'),
            Field::link('cta', 'Button below the photos'),
        ];
    }

    public function data(array $content, PageSection $section, Page $page): array
    {
        $limit = in_array((int) ($content['limit'] ?? 5), [5, 8, 10], true) ? (int) $content['limit'] : 5;

        $photos = GalleryItem::with('media', 'album')
            ->where('type', 'image')
            ->whereHas('media')
            ->whereHas('album', fn ($q) => $q->published())
            ->latest('created_at')
            ->latest('id')
            ->limit($limit)
            ->get();

        return ['photos' => $photos];
    }

    public function defaultStyle(): array
    {
        return ['background' => 'sand', 'spacing' => 'lg'];
    }
}
