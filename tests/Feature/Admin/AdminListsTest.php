<?php

namespace Tests\Feature\Admin;

use App\Models\GalleryAlbum;
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
            ->assertJsonPath('meta.to', 1);

        Package::where('slug', 'umrah-package')->update(['status' => 'draft']);

        $admin->getJson('/api/admin/packages?status=draft')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', 'umrah-package');

        $admin->getJson('/api/admin/packages?search=umrah-pack')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'umrah-package');
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
