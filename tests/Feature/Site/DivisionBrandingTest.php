<?php

namespace Tests\Feature\Site;

use App\Mail\BookingReceived;
use App\Models\Booking;
use App\Models\Package;
use Database\Seeders\PageSeeder;
use Database\Seeders\TourSeeder;
use Database\Seeders\TravelSeeder;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Admin\AdminTestCase;

/**
 * Hajj & Umrah pages are shown as the agency's division, Antarik Hajj Kafela.
 */
class DivisionBrandingTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PageSeeder::class, TravelSeeder::class, TourSeeder::class]);
        Mail::fake();
    }

    public function test_hajj_and_umrah_pages_show_the_hajj_kafela_logo_and_name(): void
    {
        foreach (['/umrah', '/hajj', '/packages/umrah-package', '/packages/umrah-package/book'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('images/brand/hajj-logo-880.webp', false)
                ->assertSee('images/brand/hajj-logo-light-880.webp', false)
                ->assertSee('<meta property="og:site_name" content="Antarik Hajj Kafela">', false)
                ->assertSee('alt="Antarik Hajj Kafela"', false);
            $this->app->forgetScopedInstances();
        }

        // The title carries the division's name; the organisation stays the agency, with the division as its department.
        $html = $this->get('/umrah')->getContent();
        $this->assertMatchesRegularExpression('/<title>[^<]*\| Antarik Hajj Kafela<\/title>/', $html);
        $this->assertStringContainsString('"name":"Antarik Travel International"', $html);
        $this->assertStringContainsString('"@id":"'.url('/#hajj-umrah').'","name":"Antarik Hajj Kafela"', $html);
    }

    public function test_other_pages_keep_the_agency_logo_and_name(): void
    {
        foreach (['/', '/tours', '/packages'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('images/brand/logo-880.webp', false)
                ->assertDontSee('hajj-logo', false)
                ->assertSee('<meta property="og:site_name" content="Antarik Travel International">', false)
                ->assertDontSee('#hajj-umrah', false);
            $this->app->forgetScopedInstances();
        }
    }

    public function test_the_name_is_translated_and_can_be_changed(): void
    {
        $this->withUnencryptedCookie('locale', 'bn')->get('/umrah')->assertOk()->assertSee('content="আন্তরিক হজ্জ কাফেলা"', false);
        $this->app->forgetScopedInstances();

        settings()->set('branding', ['hajj_name' => ['en' => 'Antarik Hajj Group']]);
        $this->get('/umrah')->assertOk()->assertSee('content="Antarik Hajj Group"', false);
    }

    public function test_hajj_and_umrah_booking_emails_use_the_hajj_kafela_name(): void
    {
        $package = Package::where('slug', 'umrah-package')->firstOrFail();

        $this->post('/packages/umrah-package/book', [
            'option' => $package->prices()->orderBy('package_prices.id')->firstOrFail()->id,
            'travelers' => 1,
            'travel_month' => now()->month,
            'travel_year' => now()->year,
            'name' => 'Abdul Karim',
            'phone' => '01711-000000',
            'email' => 'karim@example.com',
            'payment' => 'later',
            'agree' => '1',
        ])->assertRedirect();

        $email = (new BookingReceived(Booking::firstOrFail()))->render();
        $this->assertStringContainsString('alt="Antarik Hajj Kafela"', $email);
        $this->assertStringContainsString('hajj-logo-light-880.webp', $email);
    }
}
