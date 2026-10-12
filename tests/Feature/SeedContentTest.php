<?php

namespace Tests\Feature;

use App\Models\GalleryAlbum;
use App\Models\Media;
use App\Models\Package;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use Database\Seeders\BlogSeeder;
use Database\Seeders\HeroImagesSeeder;
use Database\Seeders\OldSiteMediaSeeder;
use Database\Seeders\PageSeeder;
use Database\Seeders\TravelSeeder;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Admin\AdminTestCase;

class SeedContentTest extends AdminTestCase
{
    public function test_old_site_photos_are_imported_once_and_fill_empty_slots(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $this->seed([PageSeeder::class, TravelSeeder::class, BlogSeeder::class, OldSiteMediaSeeder::class]);

        $album = GalleryAlbum::where('slug', 'hajj-workshop-2025')->firstOrFail();
        $this->assertCount(5, $album->items);
        $this->assertNotNull($album->cover_id);
        $this->assertNotNull(Service::where('slug', 'umrah')->value('image_id'));
        $this->assertNotNull(Post::where('slug', 'what-is-umrah')->value('banner_id'));

        $about = Page::where('system_key', 'about')->first()->sections()->where('component', 'about')->first();
        $this->assertNotEmpty($about->content['image']);

        // Running again imports nothing new; an image chosen by an admin is kept.
        $about->update(['content' => ['image' => 999] + $about->content]);
        $count = Media::count();
        $this->seed(OldSiteMediaSeeder::class);
        $this->assertSame($count, Media::count());
        $this->assertSame(999, $about->fresh()->content['image']);

        $this->get('/gallery/hajj-workshop-2025')->assertOk()->assertSee('Hajj workshop 2025');
    }

    public function test_every_page_top_gets_a_background_photo(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $this->seed([PageSeeder::class, TravelSeeder::class, HeroImagesSeeder::class]);

        foreach (Page::with('sections')->get() as $page) {
            $first = $page->sections->sortBy('sort')->first();

            // The gallery opens with its own centered heading instead of a banner.
            if (! in_array($first->component, ['page_banner', 'hero'], true)) {
                continue;
            }

            $this->assertNotEmpty($first->content['image'] ?? null, "{$page->slug} has no top image");
        }

        $gallery = Page::where('system_key', 'gallery')->first()->sections()->orderBy('sort')->first();
        $this->assertSame('page_listing', $gallery->component);

        $home = Page::where('system_key', 'home')->first()->sections()->orderBy('sort')->first();
        $this->assertSame('centered', $home->content['variant']);
        $this->assertNotNull(settings('branding.default_banner'));
        $this->assertSame(0, Package::whereNull('banner_id')->count());

        // Running again imports nothing new.
        $count = Media::count();
        $this->seed(HeroImagesSeeder::class);
        $this->assertSame($count, Media::count());

        $this->get('/packages')->assertOk()->assertSee(Media::find(settings('branding.default_banner'))->url('lg'), false);
    }

    public function test_policy_pages_have_bangla_text(): void
    {
        $this->seed(PageSeeder::class);

        $this->withUnencryptedCookie('locale', 'bn')->get('/terms-and-conditions')->assertOk()->assertSeeText('বুকিং ও নিবন্ধন');
        $this->withUnencryptedCookie('locale', 'bn')->get('/privacy-policy')->assertOk()->assertSeeText('আমরা যে তথ্য সংগ্রহ করি');
    }
}
