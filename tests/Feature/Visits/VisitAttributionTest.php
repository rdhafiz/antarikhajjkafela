<?php

namespace Tests\Feature\Visits;

use App\Models\Booking;
use App\Models\Inquiry;
use App\Models\Package;
use App\Models\Page;
use Database\Seeders\PageSeeder;
use Database\Seeders\TravelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Concerns\RefreshVisitLog;
use Tests\Feature\Admin\AdminTestCase;

class VisitAttributionTest extends AdminTestCase
{
    use RefreshDatabase;
    use RefreshVisitLog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PageSeeder::class, TravelSeeder::class]);
        Mail::fake();
        Http::fake();
    }

    /** @return array<string, mixed> */
    private function form(): array
    {
        $package = Package::where('slug', 'umrah-package')->firstOrFail();

        return [
            'option' => $package->prices()->orderBy('package_prices.id')->firstOrFail()->id,
            'travelers' => 2,
            'travel_month' => now()->month,
            'travel_year' => now()->year,
            'name' => 'Abdul Karim',
            'phone' => '01711-000000',
            'email' => 'karim@example.com',
            'payment' => 'later',
            'agree' => '1',
        ];
    }

    private function contactSectionId(): int
    {
        return Page::where('system_key', 'contact')->firstOrFail()->sections()->where('component', 'contact_form')->firstOrFail()->id;
    }

    private function enquiry(): array
    {
        return ['name' => 'Karim', 'phone' => '01711000000', 'email' => 'k@example.com', 'message' => 'Hello there'];
    }

    public function test_booking_stores_the_visit_ids(): void
    {
        [$vid, $sid] = [(string) Str::uuid(), (string) Str::uuid()];

        $this->withCookies(['pv_vid' => $vid, 'pv_sid' => $sid])->post('/packages/umrah-package/book', $this->form())->assertRedirect();

        $booking = Booking::firstOrFail();
        $this->assertSame($vid, $booking->visitor_id);
        $this->assertSame($sid, $booking->session_id);
    }

    public function test_enquiry_stores_the_visit_ids(): void
    {
        [$vid, $sid] = [(string) Str::uuid(), (string) Str::uuid()];

        $this->withCredentials()->withCookies(['pv_vid' => $vid, 'pv_sid' => $sid])
            ->postJson(route('forms.submit', $this->contactSectionId()), $this->enquiry())->assertSuccessful();

        $inquiry = Inquiry::firstOrFail();
        $this->assertSame($vid, $inquiry->visitor_id);
        $this->assertSame($sid, $inquiry->session_id);
    }

    public function test_missing_or_invalid_cookies_store_null(): void
    {
        $this->post('/packages/umrah-package/book', $this->form())->assertRedirect();
        $this->withCredentials()->withCookies(['pv_sid' => 'abc'])
            ->postJson(route('forms.submit', $this->contactSectionId()), $this->enquiry())->assertSuccessful();

        $booking = Booking::firstOrFail();
        $inquiry = Inquiry::firstOrFail();
        $this->assertNull($booking->visitor_id);
        $this->assertNull($booking->session_id);
        $this->assertNull($inquiry->visitor_id);
        $this->assertNull($inquiry->session_id);
    }

    public function test_admin_created_booking_has_no_visit(): void
    {
        $admin = $this->adminWith(['bookings.view', 'bookings.create']);
        $form = $this->form();

        $id = $this->withCredentials()->withCookies(['pv_vid' => (string) Str::uuid(), 'pv_sid' => (string) Str::uuid()])
            ->asAdmin($admin)->postJson('/api/admin/bookings', [
                'package_id' => Package::where('slug', 'umrah-package')->firstOrFail()->id, 'option' => $form['option'], 'travelers' => 2, 'rooms' => 1,
                'travel_month' => now()->addMonth()->month, 'travel_year' => now()->addMonth()->year,
                'name' => 'Rahim', 'phone' => '01711000009', 'email' => 'rahim@example.com', 'notify' => false,
            ])->assertCreated()->json('data.id');

        $booking = Booking::findOrFail($id);
        $this->assertNull($booking->visitor_id);
        $this->assertNull($booking->session_id);
    }
}
