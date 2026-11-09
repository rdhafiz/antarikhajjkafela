<?php

namespace Tests\Feature\Site;

use App\Models\Page;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([MenuSeeder::class, PageSeeder::class]);
    }

    public function test_live_campaigns_are_listed(): void
    {
        $campaign = Page::where('slug', 'hajj-pre-registration')->firstOrFail();
        $campaign->update(['status' => 'published', 'starts_at' => null, 'expires_at' => null]);

        $this->get('/marketing')
            ->assertOk()
            ->assertSee('href="'.url('/marketing/hajj-pre-registration').'"', false)
            ->assertSeeText($campaign->getTranslation('title', 'en'))
            ->assertSee('<link rel="canonical" href="'.url('/marketing').'">', false);

        $this->assertStringContainsString(url('/marketing').'<', $this->get('/sitemap.xml')->getContent());
    }

    public function test_draft_scheduled_and_expired_campaigns_are_hidden(): void
    {
        $campaign = Page::where('slug', 'hajj-pre-registration')->firstOrFail();
        $link = 'href="'.url('/marketing/hajj-pre-registration').'"';

        foreach ([
            ['status' => 'draft', 'starts_at' => null, 'expires_at' => null],
            ['status' => 'published', 'starts_at' => now()->addDay(), 'expires_at' => null],
            ['status' => 'published', 'starts_at' => null, 'expires_at' => now()->subMinute()],
        ] as $state) {
            $campaign->update($state);
            $this->get('/marketing')->assertOk()->assertDontSee($link, false)
                ->assertSeeText('No offers running right now');
        }
    }

    public function test_a_campaign_without_seo_image_or_description_still_renders(): void
    {
        Page::where('slug', 'hajj-pre-registration')->firstOrFail()
            ->update(['status' => 'published', 'starts_at' => null, 'expires_at' => null, 'seo' => []]);

        $this->get('/marketing')->assertOk()->assertSee('href="'.url('/marketing/hajj-pre-registration').'"', false);
    }

    public function test_the_footer_links_to_the_campaign_list(): void
    {
        $this->get('/')->assertOk()->assertSee('href="'.route('campaigns.index').'"', false)->assertSeeText('Special offers');
    }
}
