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
            ->assertSee('<title>Hajj, Umrah, Air Tickets, Visa &amp; Tour Packages from Dhaka | Antarik Travel International</title>', false)
            ->assertSee('<meta property="og:image:width" content="1200">', false)
            ->assertSee('og.jpg', false)
            ->assertSee('<meta name="twitter:image"', false);

        // The SEO title is one text (here the English one seeded for an English site), for Bangla visitors too.
        $this->withUnencryptedCookie('locale', 'bn')->get('/umrah')
            ->assertOk()
            ->assertSee('<title>Umrah Packages from Bangladesh | ', false);
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
        $about->update(['seo' => ['title' => 'Our own title', 'description' => null, 'image' => null]]);

        $this->seed(SeoSeeder::class);

        $seo = $about->fresh()->seo;
        $this->assertSame('Our own title', $seo['title']);
        $this->assertIsString($seo['description']);
        $this->assertNotEmpty($seo['description']);
        $this->assertNotEmpty($seo['image']);
    }

    public function test_seo_titles_and_descriptions_are_one_text_in_any_language(): void
    {
        $about = Page::where('system_key', 'about')->firstOrFail();
        $admin = $this->asAdmin($this->superAdmin());
        $payload = $admin->getJson("/api/admin/pages/{$about->id}")->json('data');
        $this->assertIsString($payload['seo']['title']);

        // One text, typed in Bangla here; a per-language pair is no longer accepted.
        $payload = [...$payload, 'seo' => [...$payload['seo'], 'title' => ['en' => 'About', 'bn' => 'আমাদের সম্পর্কে']]];
        $admin->putJson("/api/admin/pages/{$about->id}", $payload)->assertJsonValidationErrors('seo.title');

        $payload['seo']['title'] = '  আন্তরিক ট্রাভেল সম্পর্কে  ';
        $payload['seo']['description'] = 'ঢাকার বিশ্বস্ত ট্রাভেল এজেন্সি।';
        $admin->putJson("/api/admin/pages/{$about->id}", $payload)->assertOk()->assertJsonPath('data.seo.title', 'আন্তরিক ট্রাভেল সম্পর্কে');

        // Every visitor gets the same text, whatever language they browse in.
        $this->get('/about')->assertSee('<title>আন্তরিক ট্রাভেল সম্পর্কে | ', false)->assertSee('content="ঢাকার বিশ্বস্ত ট্রাভেল এজেন্সি।"', false);
        $this->app->forgetScopedInstances();
        $this->withUnencryptedCookie('locale', 'bn')->get('/about')->assertSee('<title>আন্তরিক ট্রাভেল সম্পর্কে | ', false);
    }

    public function test_older_per_language_seo_texts_keep_the_default_language_one(): void
    {
        settings()->set('localization', ['default_locale' => 'bn']);
        settings()->set('seo', ['meta_title' => ['en' => 'Default title', 'bn' => 'ডিফল্ট শিরোনাম'], 'meta_description' => ['en' => 'Only English']]);
        $about = Page::where('system_key', 'about')->firstOrFail();
        $about->update(['seo' => ['title' => ['en' => 'About us', 'bn' => 'আমাদের সম্পর্কে'], 'description' => ['en' => 'English only', 'bn' => ''], 'image' => null]]);

        (require database_path('migrations/2026_10_29_150000_single_language_seo_texts.php'))->up();

        $this->assertSame('আমাদের সম্পর্কে', $about->fresh()->seo['title']);
        $this->assertSame('English only', $about->fresh()->seo['description']);
        $this->assertSame('ডিফল্ট শিরোনাম', settings('seo.meta_title'));
        $this->assertSame('Only English', settings('seo.meta_description'));
    }
}
