<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Page;
use Database\Seeders\BlogSeeder;
use Database\Seeders\PageSeeder;
use Database\Seeders\SeoSeeder;
use Database\Seeders\TravelSeeder;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Admin\AdminTestCase;

class SeoTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $this->seed([PageSeeder::class, TravelSeeder::class, BlogSeeder::class, SeoSeeder::class]);
    }

    public function test_pages_get_their_own_title_description_and_jpeg_share_image(): void
    {
        $og = Media::where('usage', 'og')->firstOrFail();
        $this->assertStringEndsWith('/og.jpg', $og->variants['og']['path']);
        $this->assertSame([1200, 630], [$og->variants['og']['width'], $og->variants['og']['height']]);

        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Hajj &amp; Umrah Packages from Bangladesh | Antarik Hajj Kafela</title>', false)
            ->assertSee('<meta property="og:image:width" content="1200">', false)
            ->assertSee('og.jpg', false)
            ->assertSee('<meta name="twitter:image"', false);

        $this->withUnencryptedCookie('locale', 'bn')->get('/umrah')
            ->assertOk()
            ->assertSee('<title>বাংলাদেশ থেকে উমরাহ প্যাকেজ', false);
    }

    public function test_each_page_describes_itself_in_structured_data(): void
    {
        $this->get('/about')->assertSee('"@type":"AboutPage"', false);
        $this->get('/contact')->assertSee('"@type":"ContactPage"', false);
        $this->get('/gallery')->assertSee('"@type":"ImageGallery"', false);
        $this->get('/blog')->assertSee('"@type":"CollectionPage"', false);
        $this->get('/packages')->assertSee('"@type":"CollectionPage"', false)->assertSee('"@type":"ItemList"', false);
        $this->get('/packages/umrah-package')->assertSee('"@type":"ItemPage"', false)->assertSee('"@type":"Product"', false);

        $this->get('/hajj')
            ->assertSee('"@type":"Service"', false)
            ->assertSee('"@type":"AggregateOffer"', false)
            ->assertSee('"breadcrumb":{"@id":"'.url('/hajj').'#breadcrumb"}', false);
    }

    public function test_the_seeder_keeps_what_admins_changed(): void
    {
        $about = Page::where('system_key', 'about')->firstOrFail();
        $about->update(['seo' => ['title' => ['en' => 'Our own title'], 'description' => [], 'image' => null]]);

        $this->seed(SeoSeeder::class);

        $seo = $about->fresh()->seo;
        $this->assertSame('Our own title', $seo['title']['en']);
        $this->assertNotEmpty($seo['description']['en']);
        $this->assertNotEmpty($seo['image']);
    }
}
