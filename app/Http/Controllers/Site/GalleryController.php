<?php

namespace App\Http\Controllers\Site;

use App\Domain\Gallery\GalleryListing;
use App\Http\Controllers\Controller;
use App\Models\GalleryAlbum;
use App\Models\GalleryItem;
use App\Models\Page;
use Illuminate\Http\Response;

class GalleryController extends Controller
{
    public function show(string $slug): Response
    {
        $album = GalleryAlbum::published()->where('slug', $slug)->firstOrFail();
        $items = $album->items()->with('media')->get()->filter(fn (GalleryItem $i) => $i->type === 'video' ? $i->youtubeId() : $i->media)->values();

        abort_if($items->isEmpty(), 404);

        $locale = app()->getLocale();
        $title = $album->getTranslation('title', $locale);
        $gallery = Page::where('system_key', 'gallery')->first();
        $cover = $album->cover ?? $items->firstWhere('type', 'image')?->media;

        seo()->title($title)
            ->description($album->getTranslation('description', $locale) ?: null)
            ->canonical($album->url())
            ->pageType('ImageGallery')
            ->image($cover?->url('lg'))
            ->breadcrumb(__('Home'), url('/'))
            ->breadcrumb($gallery?->getTranslation('title', $locale) ?? __('Gallery'), $gallery?->url() ?? url('gallery'))
            ->breadcrumb($title, $album->url());

        $more = GalleryAlbum::published()->with('cover')->whereHas('items')->whereKeyNot($album->id)->newest()->limit(3)->get();
        GalleryListing::loadCovers($more);

        return response()->view('site.gallery.show', [
            'album' => $album,
            'title' => $title,
            'items' => $items,
            'cover' => $cover,
            'more' => $more,
            'galleryUrl' => $gallery?->url() ?? url('gallery'),
        ]);
    }
}
