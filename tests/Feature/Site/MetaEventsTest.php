<?php

namespace Tests\Feature\Site;

use App\Models\PageSection;
use Database\Seeders\PageSeeder;
use Database\Seeders\TourSeeder;
use Database\Seeders\TravelSeeder;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Admin\AdminTestCase;

class MetaEventsTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PageSeeder::class, TravelSeeder::class, TourSeeder::class]);
        Mail::fake();
        $this->withoutDefer();
    }

    private function livePixel(): void
    {
        settings()->set('tracking', ['facebook_pixel_id' => '1234567890', 'facebook_capi_token' => 'capi-secret']);
        $this->app['env'] = 'production';
    }

    public function test_package_and_service_pages_send_view_content_and_other_pages_do_not(): void
    {
        $this->livePixel();

        foreach (['/packages/umrah-package', '/umrah', '/tours', '/air-tickets', '/visa'] as $url) {
            $this->get($url)->assertOk()->assertSee("fbq('track','ViewContent'", false);
            $this->app->forgetScopedInstances();
        }

        foreach (['/', '/packages'] as $url) {
            $this->get($url)->assertDontSee("fbq('track','ViewContent'", false);
            $this->app->forgetScopedInstances();
        }
    }

    public function test_view_content_waits_for_the_pixel_id_in_settings(): void
    {
        $this->app['env'] = 'production';

        $this->get('/umrah')->assertOk()->assertDontSee('ViewContent', false);
    }

    public function test_a_stored_enquiry_reports_its_lead_type_but_a_bot_does_not(): void
    {
        $section = PageSection::where('component', 'lead_form')->whereHas('page', fn ($q) => $q->where('slug', 'visa'))->firstOrFail();

        $this->postJson(route('forms.submit', $section), ['website' => 'spam'])
            ->assertOk()
            ->assertJsonPath('lead', null);

        $this->postJson(route('forms.submit', $section), ['name' => 'Rahim', 'phone' => '+8801712345678', 'visa_country' => 'Thailand', 'visa_type' => 'Tourist'])
            ->assertOk()
            ->assertJsonPath('lead.content_category', 'visa_enquiry');
    }
}
