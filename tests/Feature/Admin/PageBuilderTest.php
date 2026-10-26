<?php

namespace Tests\Feature\Admin;

use App\Models\Inquiry;
use App\Models\Media;
use App\Models\Page;
use App\PageBuilder\SectionStyle;
use Database\Seeders\PageSeeder;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class PageBuilderTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PageSeeder::class);
    }

    /**
     * The editor payload for a page, as the admin SPA sends it back.
     *
     * @return array<string, mixed>
     */
    private function payload(Page $page, array $overrides = []): array
    {
        $data = $this->asAdmin($this->superAdmin())->getJson("/api/admin/pages/{$page->id}")->json('data');

        return array_replace([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'status' => $data['status'],
            'seo' => $data['seo'],
            'starts_at' => $data['starts_at'],
            'expires_at' => $data['expires_at'],
            'focused_layout' => $data['focused_layout'],
            'tracking' => $data['tracking'],
            'sections' => $data['sections'],
        ], $overrides);
    }

    private function page(string $key): Page
    {
        return Page::where('system_key', $key)->firstOrFail();
    }

    public function test_default_pages_render_their_sections(): void
    {
        $this->get('/')->assertOk()->assertSee('data-section="hero"', false)->assertSee('"@type":"FAQPage"', false);
        $this->get('/about')->assertOk()->assertSeeText('Vision & mission');
        $this->get('/contact')->assertOk()->assertSee('/forms/', false);
        $this->get('/home')->assertRedirect('/');
        $this->get('/terms-and-conditions')->assertOk()->assertSeeText('Visa processing');
        $this->get('/air-tickets')->assertOk()->assertSeeText('Request a fare quote')->assertSee('name="from_city"', false);
        $this->get('/visa')->assertOk()->assertSeeText('Which visa do you need?')->assertSee('name="visa_country"', false);
    }

    public function test_home_sections_use_soft_shades_shapes_colourful_icons_and_photo_cards(): void
    {
        $html = $this->get('/')->assertOk()
            // Neighbouring sections get their own shades and floating shapes.
            ->assertSee('bg-sky-soft', false)
            ->assertSee('bg-mint-soft', false)
            ->assertSee('class="decor pointer-events-none', false)
            // Each service card gets its own icon colour; the visa regions are photo cards.
            ->assertSee('ic-tone ic-1', false)
            ->assertSee('ic-tone ic-4', false)
            ->assertSee('photo-card group', false)
            // The fare quote's dates become calendars, and the footer credits the developer.
            ->assertSee('data-datepicker', false)
            ->assertSee('href="https://codevioso.com"', false)
            ->getContent();

        $this->assertStringContainsString('Designed and Developed by', $html);

        // An unknown background or shape style falls back to the defaults.
        $this->assertSame(['default', 'none'], array_values(array_intersect_key(
            SectionStyle::sanitize(['background' => 'neon', 'decor' => 'stars']),
            array_flip(['background', 'decor']),
        )));
    }

    public function test_inner_page_banners_use_the_shared_centered_hero(): void
    {
        $policy = Page::where('slug', 'privacy-policy')->firstOrFail();
        $banner = $policy->sections()->where('component', 'page_banner')->firstOrFail();
        $banner->update([
            'content' => array_replace($banner->content, ['highlight' => ['en' => 'since 2016.']]),
            'style' => array_replace($banner->style ?? [], ['background' => 'white']),
        ]);

        $this->get('/privacy-policy')
            ->assertOk()
            ->assertSee('hero-75', false)
            ->assertSee('data-section="page_banner"', false)
            ->assertSee('<span class="hero-line text-gold italic">since 2016.</span>', false)
            ->assertSee('data-transparent="true"', false);
    }

    public function test_hero_slides_can_each_be_an_image_with_text_or_an_image_only(): void
    {
        $image = fn (string $name) => Media::create([
            'uuid' => (string) Str::uuid(), 'usage' => 'hero', 'disk' => 'public', 'original_name' => $name,
            'directory' => 'media/test', 'filename' => $name, 'extension' => 'webp', 'mime_type' => 'image/webp', 'size' => 100, 'width' => 1600, 'height' => 900,
            'alt' => ['en' => "Alt {$name}"], 'variants' => ['lg' => ['path' => "media/test/{$name}", 'width' => 1600, 'height' => 900]],
        ])->id;

        $page = $this->page('home');
        $payload = $this->payload($page);
        $index = collect($payload['sections'])->search(fn ($s) => $s['component'] === 'hero');
        $payload['sections'][$index]['content']['variant'] = 'centered';
        $payload['sections'][$index]['content']['slides'] = [
            // The first slide can be an image only, with a phone version.
            ['type' => 'image', 'image' => $image('poster.webp'), 'image_mobile' => $image('poster-tall.webp'), 'link' => ['label' => ['en' => 'Our offer'], 'url' => '/umrah']],
            ['type' => 'text', 'image' => $image('makkah.webp'), 'title' => ['en' => 'Hajj 2028 registration'], 'highlight' => ['en' => 'is open.'], 'primary' => ['label' => ['en' => 'See packages'], 'url' => '/hajj']],
            // Only the mobile image: it is used on every screen.
            ['type' => 'image', 'image' => null, 'image_mobile' => $image('only-tall.webp')],
            // A text slide without an image shows the brand background; an empty image slide is skipped.
            ['type' => 'text', 'image' => null, 'title' => ['en' => 'Visa processing']],
            ['type' => 'image', 'image' => null, 'image_mobile' => null],
        ];

        // The type is one of the two kinds.
        $broken = $payload;
        $broken['sections'][$index]['content']['slides'][1]['type'] = 'video';
        $this->asAdmin($this->superAdmin())->putJson("/api/admin/pages/{$page->id}", $broken)
            ->assertJsonValidationErrors(["sections.{$index}.content.slides.1.type"]);

        $this->asAdmin($this->superAdmin())->putJson("/api/admin/pages/{$page->id}", $payload)->assertOk();

        $html = $this->get('/')->assertOk()
            ->assertSee('heroSlider(4)', false)
            ->assertSee('<source media="(max-width: 767px)" srcset="'.url('storage/media/test/poster-tall.webp').' 1600w"', false)
            ->assertSee('src="'.url('storage/media/test/only-tall.webp').'"', false)
            ->assertSeeText('Hajj 2028 registration')
            ->assertSeeText('Visa processing')
            ->assertSee('href="'.url('/umrah').'"', false)
            ->assertSee('aria-label="Our offer"', false)
            ->getContent();

        // The image-only first slide leaves the page heading to a hidden h1; text slides use h2.
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<h1 class="sr-only">', $html);
        $this->assertStringContainsString('<h2 class="text-display-xl text-white">', $html);
        // Image-only slides have no parallax layer.
        $onlyImage = Str::after($html, 'hs-full');
        $this->assertStringNotContainsString('data-parallax-bg', Str::before($onlyImage, '</picture>'));

        // A single slide shows on its own: no slider, no controls.
        $payload['sections'][$index]['content']['slides'] = [$payload['sections'][$index]['content']['slides'][1]];
        $this->asAdmin($this->superAdmin())->putJson("/api/admin/pages/{$page->id}", $payload)->assertOk();
        $html = $this->get('/')->assertOk()->assertDontSee('heroSlider', false)->assertDontSee('hs-dot', false)->assertSeeText('Hajj 2028 registration')->getContent();
        $this->assertStringContainsString('<h1 class="text-display-xl text-white">', $html);
    }

    public function test_heroes_saved_before_slides_keep_their_text_as_the_first_slide(): void
    {
        $hero = $this->page('home')->sections()->where('component', 'hero')->firstOrFail();
        $hero->update(['content' => [
            'variant' => 'centered',
            'title' => ['en' => 'Old headline'],
            'image' => null,
            'slides' => [['type' => 'text', 'title' => ['en' => 'Second slide']]],
        ]]);

        (require database_path('migrations/2026_10_26_100000_make_hero_text_the_first_slide.php'))->up();

        $slides = $hero->fresh()->content['slides'];
        $this->assertSame(['Old headline', 'Second slide'], array_map(fn ($s) => $s['title']['en'], $slides));
        // It gets a repeater id like every other slide, so opening the editor changes nothing.
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{4,40}$/', $slides[0]['_id']);
        $this->get('/')->assertOk()->assertSee('heroSlider(2)', false);
    }

    public function test_drafts_are_hidden_but_signed_previews_show_them(): void
    {
        $campaign = Page::where('slug', 'hajj-pre-registration')->firstOrFail();

        $this->get('/hajj-pre-registration')->assertNotFound();
        $this->get(route('pages.preview', $campaign))->assertForbidden();
        $this->get(URL::temporarySignedRoute('pages.preview', now()->addHour(), ['page' => $campaign->id]))
            ->assertOk()
            ->assertSee('noindex, nofollow', false)
            ->assertSeeText('Draft, not visible to visitors');
    }

    public function test_saving_reorders_hides_and_removes_sections(): void
    {
        $page = $this->page('services');
        $payload = $this->payload($page);
        [$banner, $features, , , $cta] = $payload['sections'];
        $features['is_visible'] = false;

        $this->asAdmin($this->superAdmin())
            ->putJson("/api/admin/pages/{$page->id}", [...$payload, 'sections' => [$cta, $banner, $features]])
            ->assertOk()
            ->assertJsonCount(3, 'data.sections')
            ->assertJsonPath('data.sections.0.component', 'cta')
            ->assertJsonPath('data.sections.2.is_visible', false);

        $this->assertSame(['cta', 'page_banner', 'features'], $page->sections()->pluck('component')->all());
        $this->get('/services')->assertDontSee('data-section="features"', false);
    }

    public function test_content_is_validated_against_the_component_fields(): void
    {
        $page = $this->page('contact');
        $payload = $this->payload($page);
        $video = count($payload['sections']);
        $cta = $video + 1;
        $payload['sections'][] = ['component' => 'video', 'content' => ['url' => 'https://evil.example.com/video'], 'style' => []];
        $payload['sections'][] = ['component' => 'cta', 'content' => ['title' => ['en' => 'Go'], 'primary' => ['url' => 'javascript:alert(1)', 'label' => ['en' => 'x']]], 'style' => ['background_color' => 'red']];

        $this->asAdmin($this->superAdmin())->putJson("/api/admin/pages/{$page->id}", $payload)
            ->assertJsonValidationErrors(["sections.{$video}.content.url", "sections.{$cta}.content.primary.url", "sections.{$cta}.style.background_color"]);
    }

    public function test_rich_text_is_sanitized(): void
    {
        $page = $this->page('contact');
        $payload = $this->payload($page);
        $payload['sections'][] = ['component' => 'rich_text', 'content' => [
            'body' => ['en' => '<p onclick="steal()">Hello <script>alert(1)</script><a href="javascript:x">link</a><strong>bold</strong></p>'],
        ], 'style' => []];

        $this->asAdmin($this->superAdmin())->putJson("/api/admin/pages/{$page->id}", $payload)->assertOk();

        $body = $page->sections()->where('component', 'rich_text')->first()->content['body']['en'];
        $this->assertStringNotContainsString('script', $body);
        $this->assertStringNotContainsString('onclick', $body);
        $this->assertStringNotContainsString('javascript', $body);
        $this->assertStringContainsString('<strong>bold</strong>', $body);
    }

    public function test_listing_sections_and_single_use_components_are_guarded(): void
    {
        $admin = $this->superAdmin();
        $gallery = $this->page('gallery');
        $payload = $this->payload($gallery);

        $withoutListing = array_values(array_filter($payload['sections'], fn ($s) => $s['component'] !== 'page_listing'));
        $this->asAdmin($admin)->putJson("/api/admin/pages/{$gallery->id}", [...$payload, 'sections' => $withoutListing])
            ->assertJsonValidationErrors('sections');

        $policy = Page::where('slug', 'privacy-policy')->firstOrFail();
        $payload = $this->payload($policy);
        $payload['sections'][] = $payload['sections'][0]; // a second page banner
        unset($payload['sections'][count($payload['sections']) - 1]['id']);

        $this->asAdmin($admin)->putJson("/api/admin/pages/{$policy->id}", $payload)->assertJsonValidationErrors('sections');
    }

    public function test_custom_pages_can_be_created_with_safe_slugs(): void
    {
        $admin = $this->superAdmin();

        $this->asAdmin($admin)->postJson('/api/admin/pages', ['type' => 'custom', 'title' => ['en' => 'Refund policy'], 'slug' => 'admin'])
            ->assertJsonValidationErrors('slug');
        $this->asAdmin($admin)->postJson('/api/admin/pages', ['type' => 'custom', 'title' => ['en' => 'Refund policy'], 'slug' => 'Refund Policy'])
            ->assertJsonValidationErrors('slug');

        $id = $this->asAdmin($admin)->postJson('/api/admin/pages', ['type' => 'custom', 'title' => ['en' => 'Refund policy'], 'slug' => 'refund-policy'])
            ->assertCreated()
            ->json('data.id');

        $this->get('/refund-policy')->assertNotFound(); // still a draft

        $page = Page::findOrFail($id);

        // A new page starts with empty sections, so it saves as a draft but
        // cannot go live until the required content is written.
        $draft = $this->payload($page);
        $this->asAdmin($admin)->putJson("/api/admin/pages/{$id}", $draft)->assertOk();
        $this->asAdmin($admin)->putJson("/api/admin/pages/{$id}", array_replace($draft, ['status' => 'published']))
            ->assertJsonValidationErrors('sections.1.content.body.en');

        $sections = $draft['sections'];
        $sections[1]['content']['body'] = ['en' => '<p>Our refund terms.</p>'];
        $this->asAdmin($admin)->putJson("/api/admin/pages/{$id}", array_replace($draft, ['status' => 'published', 'sections' => $sections]))->assertOk();
        $this->get('/refund-policy')->assertOk()->assertSeeText('Refund policy');

        $this->asAdmin($admin)->deleteJson("/api/admin/pages/{$this->page('about')->id}")->assertStatus(422);
    }

    public function test_campaign_pages_follow_their_schedule_and_redirect_when_expired(): void
    {
        $admin = $this->superAdmin();
        $campaign = Page::where('slug', 'hajj-pre-registration')->firstOrFail();

        $this->asAdmin($admin)->putJson("/api/admin/pages/{$campaign->id}", $this->payload($campaign, [
            'status' => 'published', 'starts_at' => now()->addDay()->toIso8601String(), 'expires_at' => now()->addWeek()->toIso8601String(),
        ]))->assertOk()->assertJsonPath('data.state', 'scheduled');
        $this->get('/hajj-pre-registration')->assertNotFound();

        $campaign->update(['starts_at' => now()->subDay()]);
        $this->get('/hajj-pre-registration')->assertOk()->assertSee('data-section="lead_form"', false);

        $campaign->update(['expires_at' => now()->subMinute()]);
        settings()->set('campaigns', ['expired_redirect_page' => $this->page('contact')->id]);
        $this->get('/hajj-pre-registration')->assertRedirect(url('/contact'));
    }

    public function test_campaign_and_page_permissions_are_separate(): void
    {
        $editor = $this->adminWith(['pages.view', 'pages.update']);
        $campaign = Page::where('slug', 'hajj-pre-registration')->firstOrFail();

        $this->asAdmin($editor)->getJson('/api/admin/pages?type=default')->assertOk();
        $this->asAdmin($editor)->getJson('/api/admin/pages?type=campaign')->assertForbidden();
        $this->asAdmin($editor)->getJson("/api/admin/pages/{$campaign->id}")->assertForbidden();
    }

    public function test_lead_forms_validate_the_chosen_fields_and_create_inquiries(): void
    {
        $campaign = Page::where('slug', 'hajj-pre-registration')->firstOrFail();
        $campaign->update(['status' => 'published']);
        $form = $campaign->sections()->where('component', 'lead_form')->firstOrFail();

        $this->postJson(route('forms.submit', $form), ['name' => 'Karim'])
            ->assertJsonValidationErrors(['phone', 'district']);

        $this->postJson(route('forms.submit', $form), ['name' => 'Karim', 'phone' => '01711000000', 'district' => 'Dhaka', 'package' => 'Made-up package'])
            ->assertJsonValidationErrors('package');

        $this->postJson(route('forms.submit', $form), ['name' => 'Karim', 'phone' => '01711000000', 'district' => 'Dhaka', 'package' => 'VIP Hajj package'])
            ->assertOk();

        $inquiry = Inquiry::firstOrFail();
        $this->assertSame('lead_form', $inquiry->source);
        $this->assertSame('Dhaka', $inquiry->data['district']);
        $this->assertSame($campaign->id, $inquiry->page_id);
    }

    public function test_air_ticket_and_visa_forms_check_their_choices_and_dates(): void
    {
        $air = Page::where('slug', 'air-tickets')->firstOrFail()->sections()->where('component', 'lead_form')->firstOrFail();
        $trip = ['trip_type' => 'Return', 'from_city' => 'Dhaka', 'to_city' => 'Dubai', 'adults' => 2, 'name' => 'Karim', 'phone' => '01711000000'];

        $this->postJson(route('forms.submit', $air), [...$trip, 'trip_type' => 'Rocket', 'cabin_class' => 'Cargo', 'departure_date' => now()->subDay()->toDateString()])
            ->assertJsonValidationErrors(['trip_type', 'cabin_class', 'departure_date']);
        $this->postJson(route('forms.submit', $air), [...$trip, 'departure_date' => now()->addDays(10)->toDateString(), 'return_date' => now()->addDays(5)->toDateString()])
            ->assertJsonValidationErrors(['return_date']);
        $this->postJson(route('forms.submit', $air), [...$trip, 'cabin_class' => 'Business', 'departure_date' => now()->addDays(10)->toDateString(), 'return_date' => now()->addDays(20)->toDateString()])
            ->assertOk();
        $this->assertSame('Dubai', Inquiry::latest('id')->first()->data['to_city']);

        // The visa form only takes the countries typed in the admin, in either language.
        $visa = Page::where('slug', 'visa')->firstOrFail()->sections()->where('component', 'lead_form')->firstOrFail();
        $person = ['visa_type' => 'Student', 'name' => 'Rahima', 'phone' => '01711000001'];
        $this->postJson(route('forms.submit', $visa), [...$person, 'visa_country' => 'Atlantis'])->assertJsonValidationErrors(['visa_country']);
        $this->postJson(route('forms.submit', $visa), [...$person, 'visa_country' => 'কানাডা'])->assertOk();

        // Fixed choices are shown translated and the quote form starts with sensible defaults.
        $this->withUnencryptedCookie('locale', 'bn')->get('/air-tickets')->assertOk()
            ->assertSee('<option value="Return" selected>রিটার্ন</option>', false)
            ->assertSee('name="adults" type="number"', false);
    }

    public function test_honeypot_submissions_are_silently_dropped(): void
    {
        $form = $this->page('contact')->sections()->where('component', 'contact_form')->firstOrFail();

        $this->postJson(route('forms.submit', $form), ['website' => 'http://spam', 'name' => 'Bot'])->assertOk();

        $this->assertSame(0, Inquiry::count());
    }

    public function test_forms_on_hidden_pages_or_other_sections_are_rejected(): void
    {
        $hero = $this->page('home')->sections()->where('component', 'hero')->firstOrFail();
        $draftForm = Page::where('slug', 'hajj-pre-registration')->firstOrFail()->sections()->where('component', 'lead_form')->firstOrFail();

        $this->postJson(route('forms.submit', $hero), [])->assertNotFound();
        $this->postJson(route('forms.submit', $draftForm), [])->assertNotFound();
    }

    public function test_inquiry_inbox_lists_marks_read_and_updates(): void
    {
        $form = $this->page('contact')->sections()->where('component', 'contact_form')->firstOrFail();
        $this->postJson(route('forms.submit', $form), ['name' => 'Rahim', 'phone' => '01711000000', 'message' => 'Hello'])->assertOk();
        $admin = $this->superAdmin();
        $id = Inquiry::firstOrFail()->id;

        $this->asAdmin($admin)->getJson('/api/admin/inquiries')->assertOk()->assertJsonPath('counts.unread', 1)->assertJsonPath('data.0.name', 'Rahim');
        $this->asAdmin($admin)->getJson("/api/admin/inquiries/{$id}")->assertOk()->assertJsonPath('data.read', true);
        $this->asAdmin($admin)->putJson("/api/admin/inquiries/{$id}", ['status' => 'closed', 'notes' => 'Called back'])->assertOk()->assertJsonPath('data.status', 'closed');
        $this->asAdmin($admin)->get('/api/admin/inquiries/export')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_sitemap_lists_only_public_pages(): void
    {
        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>'.url('/about').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.url('/privacy-policy').'</loc>', $xml);
        $this->assertStringNotContainsString('hajj-pre-registration', $xml);
    }

    public function test_builder_metadata_exposes_components_styles_and_icons(): void
    {
        $this->asAdmin($this->superAdmin())->getJson('/api/admin/page-builder?type=campaign')
            ->assertOk()
            ->assertJsonFragment(['key' => 'lead_form'])
            ->assertJsonPath('style_fields.0.key', 'background')
            ->assertJsonPath('icons.0.name', 'kaaba');
    }
}
