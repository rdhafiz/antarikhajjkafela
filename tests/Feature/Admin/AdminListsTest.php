<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\GalleryAlbum;
use App\Models\Inquiry;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\Page;
use App\Models\Post;
use App\Support\Locales;
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

    public function test_services_list_counts_live_packages_and_can_be_hidden_from_the_list(): void
    {
        $admin = $this->asAdmin($this->superAdmin());
        $hajj = collect($admin->getJson('/api/admin/services')->assertOk()->json('data'))->firstWhere('slug', 'hajj');

        $this->assertSame(Package::whereHas('service', fn ($q) => $q->where('slug', 'hajj'))->count(), $hajj['packages_count']);
        $this->assertSame($hajj['packages_count'], $hajj['live_packages_count']);
        $this->assertArrayHasKey('image_url', $hajj);

        // The list's "Hide from the website" action sends the row back with is_active flipped.
        $admin->putJson('/api/admin/services/'.$hajj['id'], [...$hajj, 'is_active' => false])->assertOk();

        $hidden = collect($admin->getJson('/api/admin/services')->json('data'))->firstWhere('slug', 'hajj');
        $this->assertFalse($hidden['is_active']);
        $this->assertSame(0, $hidden['live_packages_count']);
    }

    public function test_pages_list_has_counts_covers_campaign_states_and_title_sort(): void
    {
        $admin = $this->asAdmin($this->superAdmin());

        $response = $admin->getJson('/api/admin/pages?type=default')->assertOk();
        $this->assertSame(Page::where('type', 'default')->count(), $response->json('counts.types.default'));
        $this->assertSame(Page::where('type', 'default')->where('status', 'published')->count(), $response->json('counts.statuses.published'));
        $this->assertArrayHasKey('cover_url', $response->json('data.0'));
        // Built-in pages keep the menu order unless another sort is chosen.
        $this->assertSame('home', $response->json('data.0.system_key'));

        $titles = collect($admin->getJson('/api/admin/pages?type=default&sort=title')->json('data'))
            ->map(fn ($page) => $page['title'][app(Locales::class)->default()] ?? $page['title']['en'])->all();
        $sorted = $titles;
        (new \Collator(app(Locales::class)->default()))->sort($sorted);
        $this->assertSame(array_values($sorted), $titles);

        $campaign = Page::create(['type' => 'campaign', 'title' => ['en' => 'Hajj 2028 early bird'], 'slug' => 'hajj-2028-early-bird', 'status' => 'published', 'expires_at' => now()->subDay()]);
        $expired = $admin->getJson('/api/admin/pages?type=campaign&state=expired')->assertOk();
        $this->assertSame([$campaign->id], collect($expired->json('data'))->pluck('id')->all());
        $this->assertSame(1, $expired->json('counts.states.expired'));

        $admin->getJson('/api/admin/pages?type=default&sort=newest')->assertUnprocessable();
    }

    public function test_posts_list_has_counts_featured_filter_and_sorts(): void
    {
        $featured = Post::create(['title' => ['en' => 'Zamzam guide'], 'slug' => 'zamzam-guide', 'status' => 'published', 'published_at' => now()->subDay(), 'is_featured' => true, 'tags' => ['Umrah']]);
        $draft = Post::create(['title' => ['en' => 'Arafat day'], 'slug' => 'arafat-day', 'status' => 'draft']);

        $admin = $this->asAdmin($this->superAdmin());
        $response = $admin->getJson('/api/admin/posts')->assertOk();
        $this->assertSame(1, $response->json('counts.featured'));
        $this->assertSame(1, $response->json('counts.states.draft'));
        $this->assertArrayHasKey('banner_url', $response->json('data.0'));

        $ids = fn (string $query) => collect($admin->getJson('/api/admin/posts?'.$query)->assertOk()->json('data'))->pluck('id')->all();
        $this->assertSame([$featured->id], $ids('featured=yes'));
        $this->assertSame([$draft->id], $ids('featured=no'));
        $this->assertSame(['Umrah'], $admin->getJson('/api/admin/posts?featured=yes')->json('data.0.tags'));

        app()->setLocale('en');
        settings()->set('localization', ['default_locale' => 'en']);
        $this->assertSame([$draft->id, $featured->id], $ids('sort=title'));

        $admin->getJson('/api/admin/posts?sort=oldest')->assertUnprocessable();
    }

    public function test_gallery_list_filters_by_content_with_counts_and_sorts(): void
    {
        $empty = GalleryAlbum::create(['title' => ['en' => 'Badr visit'], 'slug' => 'badr-visit', 'event_date' => '2024-01-01', 'is_published' => true]);
        $videos = GalleryAlbum::create(['title' => ['en' => 'Arafat day'], 'slug' => 'arafat-day', 'event_date' => '2025-06-01', 'is_published' => false]);
        $videos->items()->create(['type' => 'video', 'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);

        $admin = $this->asAdmin($this->superAdmin());
        $response = $admin->getJson('/api/admin/gallery')->assertOk();
        $this->assertSame(1, $response->json('counts.empty'));
        $this->assertSame(1, $response->json('counts.videos'));
        $this->assertSame(1, $response->json('counts.draft'));

        $ids = fn (string $query) => collect($admin->getJson('/api/admin/gallery?'.$query)->assertOk()->json('data'))->pluck('id')->all();
        $this->assertSame([$empty->id], $ids('content=empty'));
        $this->assertSame([$videos->id], $ids('content=videos'));
        $this->assertSame([$videos->id, $empty->id], $ids('sort=newest'));
        $this->assertSame([$empty->id, $videos->id], $ids('sort=oldest'));

        // The list's "Show on the website" action sends the row back with is_published flipped.
        $row = collect($response->json('data'))->firstWhere('id', $videos->id);
        $admin->putJson('/api/admin/gallery/'.$videos->id, [...$row, 'is_published' => true])->assertOk();
        $this->assertTrue($videos->fresh()->is_published);

        $admin->getJson('/api/admin/gallery?content=audio')->assertUnprocessable();
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
