<?php

namespace Tests\Feature;

use App\Models\GalleryAlbum;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use Database\Seeders\BlogSeeder;
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

    public function test_policy_pages_have_bangla_text(): void
    {
        $this->seed(PageSeeder::class);

        $this->withUnencryptedCookie('locale', 'bn')->get('/terms-and-conditions')->assertOk()->assertSeeText('বুকিং ও নিবন্ধন');
        $this->withUnencryptedCookie('locale', 'bn')->get('/privacy-policy')->assertOk()->assertSeeText('আমরা যে তথ্য সংগ্রহ করি');
    }
}
