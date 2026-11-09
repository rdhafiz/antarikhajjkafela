<?php

namespace App\Domain\Visits;

use App\Models\GalleryAlbum;
use App\Models\Package;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;

/**
 * Tells TrackPageVisit which record the controller rendered. Only the
 * paths that return a real page call it, so redirects, 404s and previews
 * are never counted.
 */
class Visited
{
    public static function home(): void
    {
        self::set('home', null);
    }

    public static function page(Page $page): void
    {
        self::set($page->isCampaign() ? 'campaign' : 'page', $page->id);
    }

    public static function service(Service $service): void
    {
        self::set('service', $service->id);
    }

    public static function package(Package $package): void
    {
        self::set('package', $package->id);
    }

    public static function booking(Package $package): void
    {
        self::set('booking', $package->id);
    }

    public static function post(Post $post): void
    {
        self::set('post', $post->id);
    }

    public static function album(GalleryAlbum $album): void
    {
        self::set('album', $album->id);
    }

    /** @param 'packages'|'campaigns' $type */
    public static function listing(string $type): void
    {
        self::set($type, null);
    }

    private static function set(string $type, ?int $id): void
    {
        request()->attributes->set('visited', ['type' => $type, 'id' => $id]);
    }
}
