<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\GalleryAlbum;
use App\Models\Inquiry;
use App\Models\Package;
use App\Models\PackagePrice;
use Database\Seeders\PageSeeder;
use Database\Seeders\TravelSeeder;
use Illuminate\Support\Facades\Mail;

class AdminListsTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PageSeeder::class, TravelSeeder::class]);
        Mail::fake();
    }

    private function book(): void
    {
        $price = PackagePrice::query()
            ->whereHas('type', fn ($q) => $q->whereHas('package', fn ($q) => $q->where('slug', 'umrah-package')))
            ->orderBy('package_prices.id')
            ->firstOrFail();

        $this->post('/packages/umrah-package/book', [
            'option' => $price->id,
            'travelers' => 2,
            'name' => 'Abdul Karim',
            'phone' => '01711-000000',
            'email' => 'karim@example.com',
            'payment' => 'later',
            'agree' => '1',
        ])->assertRedirect();
    }

    public function test_dashboard_only_includes_modules_the_user_may_see(): void
    {
        $this->book();

        $this->asAdmin($this->superAdmin())->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('bookings.pending', 1)
            ->assertJsonPath('bookings.this_month', 1)
            ->assertJsonPath('bookings.travelers_this_month', 2)
            ->assertJsonCount(6, 'bookings.trend')
            ->assertJsonPath('bookings.trend.5.count', 1)
            ->assertJsonPath('bookings.recent.0.customer_name', 'Abdul Karim')
            ->assertJsonStructure(['inquiries' => ['new', 'recent'], 'packages' => ['published'], 'posts' => ['published'], 'gallery' => ['albums']]);

        $this->asAdmin($this->adminWith(['packages.view']))->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonMissingPath('bookings')
            ->assertJsonMissingPath('inquiries')
            ->assertJsonPath('packages.published', Package::where('status', 'published')->count());
    }

    public function test_package_list_is_paginated_and_filtered(): void
    {
        $admin = $this->asAdmin($this->superAdmin());
        $total = Package::count();

        $admin->getJson('/api/admin/packages?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', $total)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.from', 1)
            ->assertJsonPath('meta.to', 1)
            ->assertJsonStructure(['data' => [['banner_url', 'prices_count', 'hotels_count', 'flights_count', 'sections_count']]]);

        $umrah = collect($admin->getJson('/api/admin/packages?search=umrah-package')->json('data'))->firstWhere('slug', 'umrah-package');
        $package = Package::where('slug', 'umrah-package')->first();
        $this->assertSame($package->prices()->count(), $umrah['prices_count']);
        $this->assertSame(count($package->flights ?? []), $umrah['flights_count']);

        Package::where('slug', 'umrah-package')->update(['status' => 'draft']);

        $admin->getJson('/api/admin/packages?status=draft')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', 'umrah-package');

        $admin->getJson('/api/admin/packages?search=umrah-pack')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'umrah-package');
    }

    public function test_packages_filter_by_booking_state_and_sort_by_price(): void
    {
        $admin = $this->asAdmin($this->superAdmin());
        Package::where('slug', 'umrah-package')->update(['booking_starts_on' => now()->addWeek(), 'booking_ends_on' => null]);
        Package::where('slug', 'hajj-regular-package')->update(['booking_starts_on' => null, 'booking_ends_on' => now()->subDay()]);

        $slugs = fn (string $query) => collect($admin->getJson('/api/admin/packages?'.$query)->assertOk()->json('data'))->pluck('slug')->all();

        $this->assertSame(['umrah-package'], $slugs('booking=upcoming'));
        $this->assertSame(['hajj-regular-package'], $slugs('booking=closed'));
        $this->assertNotContains('umrah-package', $slugs('booking=open'));
        $this->assertNotContains('hajj-regular-package', $slugs('booking=open'));

        $prices = fn (string $sort) => collect($admin->getJson('/api/admin/packages?sort='.$sort)->json('data'))->pluck('min_price')->filter()->values()->all();
        $ascending = $prices('price_asc');
        $this->assertSame($ascending, collect($ascending)->sort()->values()->all());
        $this->assertSame(array_reverse($ascending), $prices('price_desc'));

        $admin->getJson('/api/admin/packages?booking=soon')->assertUnprocessable();
    }

    public function test_bookings_filter_by_payment_and_date_and_sort(): void
    {
        $this->book();
        $this->book();
        [$older, $newer] = Booking::orderBy('id')->get()->all();
        $older->forceFill(['created_at' => now()->subDays(10), 'paid_amount' => $older->total])->save();
        $newer->forceFill(['paid_amount' => 1000])->save();

        $admin = $this->asAdmin($this->superAdmin());
        $refs = fn (string $query) => collect($admin->getJson('/api/admin/bookings?'.$query)->assertOk()->json('data'))->pluck('reference')->all();

        $this->assertSame([$older->reference], $refs('payment=paid'));
        $this->assertSame([$newer->reference], $refs('payment=partial'));
        $this->assertSame([], $refs('payment=unpaid'));
        $this->assertSame([$newer->reference], $refs('from='.now()->subDay()->toDateString()));
        $this->assertSame([$older->reference], $refs('to='.now()->subDays(5)->toDateString()));
        $this->assertSame([$older->reference, $newer->reference], $refs('sort=oldest'));
        $this->assertSame($newer->reference, $refs('sort=balance_desc')[0]);
        $this->assertArrayHasKey('package_photo', $admin->getJson('/api/admin/bookings')->json('data.0'));

        $admin->getJson('/api/admin/bookings?from=2026-05-02&to=2026-05-01')->assertUnprocessable();
    }

    public function test_inquiries_filter_by_read_state_and_dates_with_counts(): void
    {
        $old = Inquiry::create(['source' => 'contact_form', 'name' => 'Old 100% sure', 'phone' => '01711000001', 'data' => ['message' => 'Hi'], 'status' => 'closed', 'read_at' => now()]);
        $old->forceFill(['created_at' => now()->subDays(10)])->save();
        $new = Inquiry::create(['source' => 'lead_form', 'name' => 'New lead', 'phone' => '01711000002', 'data' => ['city' => 'Dhaka'], 'status' => 'new']);

        $admin = $this->asAdmin($this->superAdmin());
        $ids = fn (string $query) => collect($admin->getJson('/api/admin/inquiries?'.$query)->assertOk()->json('data'))->pluck('id')->all();

        $this->assertSame([$new->id], $ids('read=unread'));
        $this->assertSame([$old->id], $ids('read=read'));
        $this->assertSame([$new->id], $ids('from='.now()->subDay()->toDateString()));
        $this->assertSame([$old->id], $ids('to='.now()->subDays(5)->toDateString()));
        $this->assertSame([$old->id, $new->id], $ids('sort=oldest'));
        // "%" is matched literally, not as a wildcard.
        $this->assertSame([$old->id], $ids('search=100%25'));

        $admin->getJson('/api/admin/inquiries')
            ->assertJsonPath('counts.unread', 1)
            ->assertJsonPath('counts.statuses.closed', 1)
            ->assertJsonPath('counts.sources.lead_form', 1);

        $admin->getJson('/api/admin/inquiries?read=maybe')->assertUnprocessable();
        $admin->get('/api/admin/inquiries/export?read=unread')->assertOk();
    }

    public function test_page_post_and_gallery_lists_send_full_pagination_meta(): void
    {
        GalleryAlbum::create(['title' => ['en' => 'Arafat day'], 'slug' => 'arafat-day', 'is_published' => true]);
        GalleryAlbum::create(['title' => ['en' => 'Madinah ziyarah'], 'slug' => 'madinah-ziyarah', 'is_published' => false]);

        $admin = $this->asAdmin($this->superAdmin());

        $admin->getJson('/api/admin/pages?type=default&per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.from', 1)
            ->assertJsonPath('meta.to', 2);

        $admin->getJson('/api/admin/posts')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to']]);

        $admin->getJson('/api/admin/gallery?status=draft')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', 'madinah-ziyarah');
    }

    public function test_phone_numbers_are_validated_with_their_country(): void
    {
        $user = $this->superAdmin();

        $this->asAdmin($user)->putJson('/api/admin/profile', ['name' => 'Admin', 'email' => $user->email, 'phone' => '12345'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        $this->asAdmin($user)->putJson('/api/admin/profile', ['name' => 'Admin', 'email' => $user->email, 'phone' => '+880 1711-381901'])
            ->assertOk();

        $this->asAdmin($user)->putJson('/api/admin/profile', ['name' => 'Admin', 'email' => $user->email, 'phone' => '01711-381901'])
            ->assertOk();
    }
}
