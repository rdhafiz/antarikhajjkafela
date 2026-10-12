<?php

namespace App\PageBuilder\Components;

use App\Domain\Gallery\GalleryListing;
use App\Models\GalleryAlbum;
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
        return 'The newest gallery albums, or a photo mosaic when there is only one album.';
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
            Field::select('limit', 'Size', ['5' => 'Small: 3 albums or 5 photos', '8' => 'Medium: 6 albums or 8 photos', '10' => 'Large: 6 albums or 10 photos'])->default('5')
                ->hint('With several albums the newest albums are shown; with only one album, its photos fill the section.'),
            Field::link('cta', 'Button below the photos'),
        ];
    }

    public function data(array $content, PageSection $section, Page $page): array
    {
        $limit = in_array((int) ($content['limit'] ?? 5), [5, 8, 10], true) ? (int) $content['limit'] : 5;

        $albums = GalleryAlbum::published()
            ->whereHas('items')
            ->with('cover')
            ->withCount([
                'items as images_count' => fn ($q) => $q->where('type', 'image'),
                'items as videos_count' => fn ($q) => $q->where('type', 'video'),
            ])
            ->newest()
            ->limit($limit >= 8 ? 6 : 3)
            ->get();

        // Several albums: show the albums themselves.
        if ($albums->count() > 1) {
            GalleryListing::loadCovers($albums);

            return ['albums' => $albums, 'photos' => collect()];
        }

        // One album (or none): its photos fill the section.
        $photos = GalleryItem::with('media', 'album')
            ->where('type', 'image')
            ->whereHas('media')
            ->whereIn('album_id', $albums->pluck('id'))
            ->latest('created_at')
            ->latest('id')
            ->limit($limit)
            ->get();

        return ['albums' => collect(), 'photos' => $photos];
    }

    public function defaultStyle(): array
    {
        return ['background' => 'sand', 'spacing' => 'lg'];
    }
}
