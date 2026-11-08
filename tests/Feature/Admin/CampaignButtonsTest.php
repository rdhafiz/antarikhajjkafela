<?php

namespace Tests\Feature\Admin;

use App\Models\Page;
use Database\Seeders\PageSeeder;
use Illuminate\Support\Facades\URL;

class CampaignButtonsTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PageSeeder::class);
    }

    private function campaign(): Page
    {
        $id = $this->asAdmin($this->superAdmin())
            ->postJson('/api/admin/pages', ['type' => 'campaign', 'title' => ['en' => 'Umrah offer'], 'slug' => 'umrah-offer'])
            ->assertCreated()
            ->json('data.id');

        return Page::findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Page $page): array
    {
        $data = $this->asAdmin($this->superAdmin())->getJson("/api/admin/pages/{$page->id}")->json('data');

        // What a published campaign needs filled in.
        foreach ($data['sections'] as &$section) {
            match ($section['component']) {
                'faq' => $section['content']['items'] = [['question' => ['en' => 'When do we fly?'], 'answer' => ['en' => 'In March.']]],
                'cta' => $section['content']['title'] = ['en' => 'Ready to go?'],
                default => null,
            };
        }
        unset($section);

        return [
            'title' => $data['title'],
            'slug' => $data['slug'],
            'status' => Page::STATUS_PUBLISHED,
            'seo' => $data['seo'],
            'starts_at' => null,
            'expires_at' => null,
            'focused_layout' => false,
            'tracking' => [],
            'sections' => $data['sections'],
        ];
    }

    private function section(array $payload, string $component): int
    {
        return collect($payload['sections'])->search(fn ($s) => $s['component'] === $component);
    }

    public function test_campaign_sections_get_a_bottom_button_and_other_pages_do_not(): void
    {
        $admin = $this->superAdmin();
        $fields = fn (string $type, string $key) => collect($this->asAdmin($admin)->getJson("/api/admin/page-builder?type={$type}")->json('components'))
            ->firstWhere('key', $key)['fields'];

        $campaignLead = collect($fields('campaign', 'lead_form'))->pluck('key');
        $this->assertContains('cta', $campaignLead);
        $this->assertContains('cta_align', $campaignLead);
        $this->assertNotContains('cta', collect($fields('custom', 'lead_form'))->pluck('key'));

        // A component with a button of its own gets the alignment, right after it.
        $about = collect($fields('campaign', 'about'))->pluck('key')->values();
        $this->assertSame($about->search('link') + 1, $about->search('link_align'));

        // The hero, call to action and image with text keep their own two buttons.
        foreach (['hero', 'cta', 'image_text'] as $key) {
            $this->assertNotContains('cta_align', collect($fields('campaign', $key))->pluck('key'));
        }
    }

    public function test_buttons_jump_to_a_section_and_sit_at_the_bottom_with_their_alignment(): void
    {
        $page = $this->campaign();
        $payload = $this->payload($page);
        $lead = $this->section($payload, 'lead_form');
        $faq = $this->section($payload, 'faq');
        $hero = $this->section($payload, 'hero');
        $leadRef = $payload['sections'][$lead]['style']['ref'];
        $this->assertMatchesRegularExpression('/^[a-z0-9]{10}$/', $leadRef);

        $payload['sections'][$faq]['content']['cta'] = ['label' => ['en' => 'Book your seat'], 'url' => null, 'section' => $leadRef];
        $payload['sections'][$faq]['content']['cta_align'] = 'right';
        $payload['sections'][$lead]['content']['cta'] = ['label' => ['en' => 'Read the terms'], 'url' => 'https://example.com/terms'];
        $payload['sections'][$lead]['content']['cta_align'] = 'left';
        $payload['sections'][$hero]['content']['primary'] = ['label' => ['en' => 'Register now'], 'url' => null, 'section' => $leadRef];
        $payload['sections'][$hero]['content']['secondary'] = ['label' => ['en' => ''], 'url' => '/contact'];

        $this->asAdmin($this->superAdmin())->putJson("/api/admin/pages/{$page->id}", $payload)->assertOk();

        $html = $this->get('/marketing/umrah-offer')->assertOk()->getContent();

        $this->assertStringContainsString('id="s-'.$leadRef.'"', $html);
        $this->assertSame(2, substr_count($html, 'href="#s-'.$leadRef.'"'), 'Hero and FAQ buttons jump to the form.');
        $this->assertMatchesRegularExpression('/justify-end"\s*data-reveal\s*>\s*<a[^>]+href="#s-'.$leadRef.'"/', $html);
        $this->assertMatchesRegularExpression('/justify-start"\s*data-reveal\s*>\s*<a[^>]+href="https:\/\/example.com\/terms"/', $html);
        // A button without text is not shown.
        $this->assertStringNotContainsString('href="'.url('/contact').'"', $html);
    }

    public function test_a_button_to_a_removed_section_disappears_and_refs_stay_unique(): void
    {
        $page = $this->campaign();
        $payload = $this->payload($page);
        $lead = $this->section($payload, 'lead_form');
        $faq = $this->section($payload, 'faq');
        $leadRef = $payload['sections'][$lead]['style']['ref'];

        $payload['sections'][$faq]['content']['cta'] = ['label' => ['en' => 'Book your seat'], 'url' => null, 'section' => $leadRef];
        // A copied section arrives with the same reference: it gets its own.
        $copy = $payload['sections'][$faq];
        unset($copy['id']);
        $payload['sections'][] = $copy;
        array_splice($payload['sections'], $lead, 1);

        $this->asAdmin($this->superAdmin())->putJson("/api/admin/pages/{$page->id}", $payload)->assertOk();

        $refs = $page->sections()->get()->pluck('style.ref');
        $this->assertSame($refs->count(), $refs->unique()->count());
        $this->assertStringNotContainsString('Book your seat', $this->get('/marketing/umrah-offer')->assertOk()->getContent());
    }

    public function test_a_button_never_jumps_to_its_own_section(): void
    {
        $page = $this->campaign();
        $payload = $this->payload($page);
        $faq = $this->section($payload, 'faq');
        $payload['sections'][$faq]['content']['cta'] = ['label' => ['en' => 'Loop back'], 'url' => null, 'section' => $payload['sections'][$faq]['style']['ref']];

        $this->asAdmin($this->superAdmin())->putJson("/api/admin/pages/{$page->id}", $payload)->assertOk();

        $this->assertStringNotContainsString('Loop back', $this->get('/marketing/umrah-offer')->assertOk()->getContent());
    }

    public function test_link_targets_must_be_references(): void
    {
        $page = $this->campaign();
        $payload = $this->payload($page);
        $payload['sections'][$this->section($payload, 'faq')]['content']['cta'] = ['label' => ['en' => 'Go'], 'section' => 'javascript:alert(1)'];

        $this->asAdmin($this->superAdmin())->putJson("/api/admin/pages/{$page->id}", $payload)->assertUnprocessable();
    }

    public function test_custom_pages_keep_their_buttons_where_they_were(): void
    {
        $html = $this->get(URL::to('/air-tickets'))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="s-', $html);
    }

    public function test_the_page_banner_shows_its_button_inside_the_banner(): void
    {
        $page = $this->campaign();
        $page->update(['status' => Page::STATUS_PUBLISHED, 'starts_at' => null, 'expires_at' => null]);
        $page->sections()->get()->each->delete();
        $page->sections()->create([
            'component' => 'page_banner',
            'sort' => 0,
            'is_visible' => true,
            'content' => ['title' => ['en' => 'Umrah offer'], 'cta' => ['label' => ['en' => 'Book a seat'], 'url' => '/contact', 'section' => null], 'cta_align' => 'right'],
            'style' => ['ref' => 'bannerref1'],
        ]);

        $this->get('/marketing/umrah-offer')->assertOk()
            ->assertSee('Book a seat')
            ->assertSee('justify-end', false);
    }
}
