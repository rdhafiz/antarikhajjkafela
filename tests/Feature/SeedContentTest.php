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
use Database\Seeders\MenuSeeder;
use Database\Seeders\OldSiteMediaSeeder;
use Database\Seeders\PageSeeder;
use Database\Seeders\TourSeeder;
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

            // About, contact, gallery and blog open with their own heading instead of a banner.
            if (! in_array($first->component, ['page_banner', 'hero'], true)) {
                continue;
            }

            $this->assertNotEmpty($first->content['image'] ?? null, "{$page->slug} has no top image");
        }

        $this->assertSame('about', Page::where('system_key', 'about')->first()->sections()->orderBy('sort')->first()->component);
        $this->assertSame('contact_form', Page::where('system_key', 'contact')->first()->sections()->orderBy('sort')->first()->component);
        foreach (['gallery', 'blog'] as $key) {
            $this->assertSame('page_listing', Page::where('system_key', $key)->first()->sections()->orderBy('sort')->first()->component);
        }

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

    public function test_sample_tours_are_bookable_and_seeded_once(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $this->seed(TourSeeder::class);

        $tours = Service::where('slug', 'tours')->firstOrFail();
        $this->assertSame('month_year', $tours->travel_date);
        $this->assertFalse($tours->isPilgrimage());
        $this->assertSame(count(TourSeeder::tours()), $tours->packages()->published()->count());
        $this->assertSame(0, $tours->packages()->whereNull('banner_id')->count());

        $package = Package::where('slug', 'thailand-bangkok-pattaya')->firstOrFail();
        $this->assertSame(52900, (int) $package->min_price);
        $this->get('/tours')->assertOk()->assertSeeText('Thailand: Bangkok & Pattaya')->assertSeeText('Choose your next trip')->assertDontSeeText('Hotels near the Haram');
        $this->get("/packages/{$package->slug}/book")->assertOk()->assertSee('images/booking/sky-scene.webp', false);

        // A second run (or a trashed Tours service) leaves the admin's tours alone.
        $tours->delete();
        $this->seed(TourSeeder::class);
        $this->assertSame(0, Service::where('slug', 'tours')->count());
        $this->assertSame(1, Service::onlyTrashed()->count());
    }

    public function test_rebrand_rebuilds_the_general_pages_and_menus_and_keeps_old_sections_in_the_trash(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $this->seed([PageSeeder::class, TravelSeeder::class, MenuSeeder::class]);
        $home = Page::where('system_key', 'home')->firstOrFail();
        $old = $home->sections()->pluck('id');
        settings()->set('branding', ['site_name' => ['en' => 'Antarik Hajj Kafela', 'bn' => 'আন্তরিক হজ্জ কাফেলা']]);

        $this->artisan('site:rebrand', ['--force' => true])->assertSuccessful();

        $this->assertSame('Antarik Travel International', settings('branding.site_name')['en'] ?? settings('branding.site_name'));
        $this->assertSame(0, $home->sections()->whereIn('id', $old)->count());
        $this->assertSame($old->count(), $home->sections()->onlyTrashed()->whereIn('id', $old)->count());
        $this->assertNotNull(Service::where('slug', 'umrah')->first(), 'Hajj and Umrah services are kept');
        $this->assertTrue(Service::where('slug', 'tours')->exists());

        $showcase = $home->sections()->where('component', 'package_showcase')->firstOrFail();
        $this->assertSame((string) Service::where('slug', 'tours')->value('id'), (string) $showcase->content['service']);

        $this->get('/')->assertOk()->assertSee('href="'.url('/air-tickets').'"', false)->assertSee('href="'.url('/tours').'"', false);
        $this->get('/visa')->assertOk();
    }

    public function test_policy_pages_have_bangla_text(): void
    {
        $this->seed(PageSeeder::class);

        $this->withUnencryptedCookie('locale', 'bn')->get('/terms-and-conditions')->assertOk()->assertSeeText('ভিসা প্রসেসিং');
        $this->withUnencryptedCookie('locale', 'bn')->get('/privacy-policy')->assertOk()->assertSeeText('আমরা যে তথ্য সংগ্রহ করি');
    }
}
