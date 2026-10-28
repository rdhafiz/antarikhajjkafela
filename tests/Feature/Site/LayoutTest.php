<?php

namespace Tests\Feature\Site;

use App\Domain\Access\AccessSynchronizer;
use App\Domain\Media\MediaUploader;
use App\Models\User;
use App\Support\SiteContext;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([MenuSeeder::class, PageSeeder::class]);
    }

    public function test_home_renders_the_header_menu_and_footer(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="en"', false)
            ->assertSeeText('Umrah')
            ->assertSeeText('Find your booking')
            ->assertSee('href="tel:+8801827176727"', false)
            ->assertSee('https://wa.me/8801827176727', false)
            ->assertSee('https://www.facebook.com/antariktravelinternational/', false)
            ->assertSee('https://m.me/antariktravelinternational', false)
            ->assertSee('aria-label="Messenger"', false);
    }

    public function test_the_language_switcher_is_only_in_the_header(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'aria-label="Language"'));
        $footer = substr($html, strpos($html, '<footer'));
        $this->assertStringNotContainsString('aria-label="Language"', $footer);
        $this->assertStringNotContainsString('/language/', $footer);
    }

    public function test_the_header_shows_the_light_logo_over_dark_page_tops(): void
    {
        // No uploaded logo: the bundled light logo shows over the hero, no white panel.
        $this->get('/')->assertOk()
            ->assertSee('images/brand/logo-light-880.webp', false)
            ->assertSee('logo-light pointer-events-none', false)
            ->assertDontSee('header:bg-white group-data-[transparent=true]/header:shadow', false);

        // A custom logo without a light version falls back to the white panel.
        Storage::fake('public');
        Storage::fake('local');
        $logo = app(MediaUploader::class)->upload(UploadedFile::fake()->image('other-brand.png', 600, 200), 'logo');
        settings()->set('branding', ['logo' => $logo->id]);
        $this->app->forgetScopedInstances();

        $this->get('/')->assertOk()
            ->assertDontSee('logo-light pointer-events-none', false)
            ->assertSee('header:bg-white group-data-[transparent=true]/header:shadow', false);

        // With its light version uploaded, that one is used.
        $light = app(MediaUploader::class)->upload(UploadedFile::fake()->image('other-brand-light.png', 600, 200), 'logo');
        settings()->set('branding', ['logo_light' => $light->id]);
        $this->app->forgetScopedInstances();

        $this->get('/')->assertOk()->assertSee($light->url('md'), false);
    }

    public function test_seo_head_and_structured_data_are_present(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<title>Antarik Travel International | Hajj, Umrah, Air Tickets, Visa &amp; Tours</title>', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.url('/').'">', $html);
        $this->assertStringContainsString('<meta property="og:title"', $html);
        $this->assertStringContainsString('<meta name="robots" content="index, follow', $html);

        preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $html, $match);
        $graph = json_decode($match[1], true)['@graph'];

        $this->assertSame('TravelAgency', $graph[0]['@type']);
        $this->assertSame('Antarik Travel International', $graph[0]['name']);
        $this->assertSame('BD', $graph[0]['address']['addressCountry']);
        $this->assertSame('WebSite', $graph[1]['@type']);
    }

    public function test_extra_schema_from_settings_is_appended(): void
    {
        settings()->set('seo', ['schema' => json_encode(['@type' => 'FAQPage', 'name' => 'Extra'])]);

        $this->get('/')->assertSee('"@type":"FAQPage"', false);
    }

    public function test_bangla_visitors_get_bangla_markup_and_labels(): void
    {
        $this->withUnencryptedCookie('locale', 'bn')
            ->get('/')
            ->assertOk()
            ->assertSee('<html lang="bn"', false)
            ->assertSeeText('উমরাহ')
            ->assertSeeText('ট্যুর দেখুন')
            ->assertSee('<meta property="og:locale" content="bn_BD">', false);
    }

    public function test_hidden_and_unlinked_footer_items_are_not_rendered(): void
    {
        $this->asSuperAdmin()->putJson('/api/admin/menus/footer', ['items' => [
            ['label' => ['en' => 'Visible link'], 'link_type' => 'route', 'link_target' => 'about'],
            ['label' => ['en' => 'Hidden link'], 'link_type' => 'route', 'link_target' => 'contact', 'is_visible' => false],
        ]])->assertOk();

        $this->get('/')->assertSeeText('Visible link')->assertDontSeeText('Hidden link');
    }

    public function test_tracking_tags_are_only_output_in_production(): void
    {
        settings()->set('tracking', ['gtm_id' => 'GTM-ABC123', 'facebook_pixel_id' => '1234567890']);

        $this->get('/')->assertDontSee('googletagmanager.com', false);

        $this->app['env'] = 'production';
        $this->get('/')
            ->assertSee('googletagmanager.com/gtm.js', false)
            ->assertSee('"GTM-ABC123"', false)
            ->assertSee("fbq('init',\"1234567890\")", false);
    }

    public function test_missing_pages_use_the_branded_404(): void
    {
        $this->get('/does-not-exist')
            ->assertNotFound()
            ->assertSeeText('This page could not be found')
            ->assertSee('noindex, nofollow', false);
    }

    public function test_phone_numbers_are_formatted_for_links(): void
    {
        settings()->set('contact', ['primary_whatsapp' => '+880 1814-371475', 'primary_phone' => '8801711381901']);
        $site = app(SiteContext::class);

        $this->assertSame('https://wa.me/8801814371475', $site->whatsappUrl());
        $this->assertSame('tel:+8801711381901', $site->phones()[0]['href']);
        $this->assertStringEndsWith('?text=Salam%20%26%20hello', $site->whatsappUrl('Salam & hello'));
    }

    private function asSuperAdmin(): static
    {
        app(AccessSynchronizer::class)->sync();
        $user = User::factory()->create()->assignRole('super-admin');

        return $this->actingAs($user, 'web')->withHeaders(['Referer' => config('app.url')]);
    }
}
