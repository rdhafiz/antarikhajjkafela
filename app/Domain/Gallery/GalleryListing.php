<?php

namespace App\Domain\Gallery;

use App\Models\GalleryAlbum;
use App\Models\GalleryItem;
use App\Models\Page;

/**
 * Published albums on the Gallery page, newest first.
 */
class GalleryListing
{
    public const PER_PAGE = 12;

    public function view(): string
    {
        return 'site.gallery.listing';
    }

    /**
     * @return array<string, mixed>
     */
    public function data(Page $page): array
    {
        $albums = GalleryAlbum::published()
            ->with('cover')
            ->withCount([
                'items as images_count' => fn ($q) => $q->where('type', 'image'),
                'items as videos_count' => fn ($q) => $q->where('type', 'video'),
            ])
            ->whereHas('items')
            ->newest()
            ->paginate(self::PER_PAGE)
            ->withPath($page->url());

        self::loadCovers($albums->getCollection());

        return ['albums' => $albums];
    }

    /**
     * Give albums without a chosen cover their newest photo (one query).
     *
     * @param  iterable<GalleryAlbum>  $albums
     */
    public static function loadCovers(iterable $albums): void
    {
        $albums = collect($albums);
        $missing = $albums->whereNull('cover_id')->pluck('id');

        if ($missing->isEmpty()) {
            return;
        }

        $firsts = GalleryItem::with('media')
            ->where('type', 'image')
            ->whereIn('album_id', $missing)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->unique('album_id')
            ->keyBy('album_id');

        foreach ($albums as $album) {
            if (! $album->cover_id && isset($firsts[$album->id])) {
                $album->setRelation('cover', $firsts[$album->id]->media);
            }
        }
    }
}
