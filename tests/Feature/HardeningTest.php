<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Inquiry;
use App\Models\Package;
use Database\Seeders\PageSeeder;
use Database\Seeders\TravelSeeder;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Admin\AdminTestCase;

class HardeningTest extends AdminTestCase
{
    public function test_security_headers_are_sent(): void
    {
        $this->seed(PageSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $this->getJson('/api/admin/auth/me')->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_the_private_booking_page_never_leaks_its_link(): void
    {
        Mail::fake();
        $this->seed([PageSeeder::class, TravelSeeder::class]);
        $package = Package::where('slug', 'umrah-package')->firstOrFail();

        $this->post('/packages/umrah-package/book', [
            'travel_month' => now()->month, 'travel_year' => now()->year, 'option' => $package->prices()->first()->id, 'travelers' => 1, 'name' => 'Test', 'phone' => '01711000000', 'email' => 'test@example.com',
            'payment' => 'later', 'agree' => '1',
        ]);

        $this->get(Booking::firstOrFail()->publicUrl())->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_booking_lookup_is_limited_per_contact(): void
    {
        foreach (range(1, 5) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                ->post('/booking/status', ['reference' => "AHK-2026-00000{$i}", 'contact' => '01711-000000'])
                ->assertSessionHasErrors('reference');
        }

        // A sixth guess for the same phone number is refused, even from a new address.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->post('/booking/status', ['reference' => 'AHK-2026-000009', 'contact' => '+8801711000000'])
            ->assertStatus(429);
    }

    public function test_csv_exports_neutralise_formulas(): void
    {
        Inquiry::create(['source' => 'contact_form', 'name' => '=HYPERLINK("http://evil.test","x")', 'phone' => '+8801711000000', 'data' => []]);

        $csv = $this->asAdmin($this->superAdmin())->get('/api/admin/inquiries/export')->assertOk()->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);

        // Phone numbers go out as ="…" so Excel keeps the leading zero. Only
        // phone characters survive the wrapper, so it cannot carry a formula.
        $row = str_getcsv(explode("\n", trim($csv))[1]);
        $this->assertSame('="+8801711000000"', $row[4]);

        Inquiry::create(['source' => 'contact_form', 'name' => 'Formula in the phone', 'phone' => '=HYPERLINK("http://evil.test","x")', 'data' => []]);
        $this->assertStringNotContainsString('HYPERLINK("http', $this->asAdmin($this->superAdmin())->get('/api/admin/inquiries/export')->streamedContent());
    }

    public function test_language_switch_only_returns_to_this_site(): void
    {
        $host = parse_url(config('app.url'), PHP_URL_HOST);

        $this->get('/language/bn', ['Referer' => "http://{$host}.evil.test/phish"])->assertRedirect(url('/'));
        $this->get('/language/bn', ['Referer' => url('/about')])->assertRedirect(url('/about'));
    }

    public function test_error_pages_render_without_the_database(): void
    {
        foreach ([500, 503, 419, 429] as $code) {
            $html = view("errors.{$code}")->render();
            $this->assertStringContainsString((string) $code, $html);
            $this->assertStringContainsString('noindex', $html);
        }
    }
}
