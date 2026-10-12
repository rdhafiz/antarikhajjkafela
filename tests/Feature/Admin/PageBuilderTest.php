<?php

namespace Tests\Feature\Admin;

use App\Models\Inquiry;
use App\Models\Page;
use Database\Seeders\PageSeeder;
use Illuminate\Support\Facades\URL;

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
        $this->get('/terms-and-conditions')->assertOk()->assertSeeText('Booking and registration');
    }

    public function test_inner_page_banners_use_the_shared_centered_hero(): void
    {
        $contact = $this->page('contact');
        $banner = $contact->sections()->where('component', 'page_banner')->firstOrFail();
        $banner->update([
            'content' => array_replace($banner->content, ['highlight' => ['en' => 'since 2016.']]),
            'style' => array_replace($banner->style ?? [], ['background' => 'white']),
        ]);

        $this->get('/contact')
            ->assertOk()
            ->assertSee('hero-75', false)
            ->assertSee('data-section="page_banner"', false)
            ->assertSee('<span class="hero-line text-gold italic">since 2016.</span>', false)
            ->assertSee('data-transparent="true"', false);
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
        [$banner, $features, $process, $cta] = $payload['sections'];
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
        $payload['sections'][] = ['component' => 'video', 'content' => ['url' => 'https://evil.example.com/video'], 'style' => []];
        $payload['sections'][] = ['component' => 'cta', 'content' => ['title' => ['en' => 'Go'], 'primary' => ['url' => 'javascript:alert(1)', 'label' => ['en' => 'x']]], 'style' => ['background_color' => 'red']];

        $this->asAdmin($this->superAdmin())->putJson("/api/admin/pages/{$page->id}", $payload)
            ->assertJsonValidationErrors(['sections.3.content.url', 'sections.4.content.primary.url', 'sections.4.style.background_color']);
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

        $contact = $this->page('contact');
        $payload = $this->payload($contact);
        $payload['sections'][] = $payload['sections'][0]; // a second page banner
        unset($payload['sections'][count($payload['sections']) - 1]['id']);

        $this->asAdmin($admin)->putJson("/api/admin/pages/{$contact->id}", $payload)->assertJsonValidationErrors('sections');
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
        $this->asAdmin($admin)->putJson("/api/admin/pages/{$id}", $this->payload($page, ['status' => 'published']))->assertOk();
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
