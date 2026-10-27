<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\GalleryAlbum;
use App\Models\GalleryItem;
use App\Models\Package;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\User;
use Database\Seeders\PageSeeder;
use Database\Seeders\TravelSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class TrashTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PageSeeder::class, TravelSeeder::class]);
    }

    public function test_deleted_records_go_to_the_trash_and_come_back(): void
    {
        $admin = $this->superAdmin(['name' => 'Office Manager']);
        $package = Package::where('slug', 'umrah-package')->firstOrFail();

        $this->asAdmin($admin)->deleteJson("/api/admin/packages/{$package->id}")->assertNoContent();

        // Gone from the website and the lists, kept in the database with who deleted it.
        $this->assertSoftDeleted($package);
        $this->get('/packages/umrah-package')->assertNotFound();
        $this->assertSame($admin->id, $package->fresh()->deleted_by);
        $this->assertSame('umrah-package', $package->fresh()->trashed_values['slug']);

        $this->asAdmin($admin)->getJson('/api/admin/trash')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'packages')
            ->assertJsonPath('data.0.title', 'Umrah Package')
            ->assertJsonPath('data.0.deleted_by', 'Office Manager')
            ->assertJsonPath('types.3.key', 'packages')
            ->assertJsonPath('types.3.count', 1);

        $this->asAdmin($admin)->postJson("/api/admin/trash/packages/{$package->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.link', "/admin/packages/{$package->id}");

        $this->assertNotSoftDeleted($package);
        $this->assertSame('umrah-package', $package->fresh()->slug);
        $this->get('/packages/umrah-package')->assertOk();
        $this->asAdmin($admin)->getJson('/api/admin/trash')->assertJsonCount(0, 'data');
    }

    public function test_addresses_and_emails_are_freed_while_in_the_trash(): void
    {
        $page = Page::where('slug', 'privacy-policy')->firstOrFail();
        $page->delete();

        // A new page may take the address; the restored one then gets a numbered address.
        $page->replicate(['deleted_at', 'deleted_by', 'trashed_values'])->fill(['slug' => 'privacy-policy'])->save();
        $page->restore();
        $this->assertSame('privacy-policy-2', $page->fresh()->slug);

        // An email in use again blocks restoring the old account.
        $old = User::factory()->create(['email' => 'staff@example.com']);
        $old->delete();
        User::factory()->create(['email' => 'staff@example.com']);

        $this->asAdmin($this->superAdmin())->postJson("/api/admin/trash/users/{$old->id}/restore")->assertJsonValidationErrors('email');
        $this->assertSoftDeleted($old);
    }

    public function test_a_booking_comes_back_with_its_documents(): void
    {
        Mail::fake();
        Storage::fake('local');

        $this->post('/packages/umrah-package/book', [
            'option' => Package::where('slug', 'umrah-package')->firstOrFail()->prices()->value('package_prices.id'),
            'travelers' => 1, 'travel_month' => now()->month, 'travel_year' => now()->year, 'name' => 'Abdul Karim', 'phone' => '01711-000000', 'email' => 'karim@example.com', 'payment' => 'later', 'agree' => '1',
            'passport' => [UploadedFile::fake()->image('passport.jpg')],
        ])->assertRedirect();
        $booking = Booking::firstOrFail();
        $document = $booking->documents()->firstOrFail();

        $admin = $this->superAdmin();
        $this->asAdmin($admin)->deleteJson("/api/admin/bookings/{$booking->id}")->assertNoContent();
        $this->assertSoftDeleted($document);
        Storage::disk('local')->assertExists($document->path);

        $this->asAdmin($admin)->postJson("/api/admin/trash/bookings/{$booking->id}/restore")->assertOk();
        $this->assertNotSoftDeleted($booking);
        $this->assertNotSoftDeleted($document);
    }

    public function test_children_wait_for_their_parent(): void
    {
        $album = GalleryAlbum::create(['title' => ['en' => 'Arafat'], 'slug' => 'arafat']);
        $item = $album->items()->create(['type' => 'video', 'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);
        $item->delete();
        $album->delete();

        $admin = $this->superAdmin();
        $row = collect($this->asAdmin($admin)->getJson('/api/admin/trash?type=gallery_items')->json('data'))->firstWhere('id', $item->id);
        $this->assertSame('Restore its album first.', $row['blocker']);
        $this->asAdmin($admin)->postJson("/api/admin/trash/gallery_items/{$item->id}/restore")->assertStatus(422);

        $this->asAdmin($admin)->postJson("/api/admin/trash/gallery_albums/{$album->id}/restore")->assertOk();
        $this->asAdmin($admin)->postJson("/api/admin/trash/gallery_items/{$item->id}/restore")->assertOk();
        $this->assertNotSoftDeleted(GalleryItem::find($item->id));
    }

    public function test_sections_removed_while_editing_are_kept(): void
    {
        $home = Page::where('system_key', 'home')->firstOrFail();
        $admin = $this->superAdmin();
        $data = $this->asAdmin($admin)->getJson("/api/admin/pages/{$home->id}")->json('data');
        $removed = $data['sections'][1]['id'];

        $this->asAdmin($admin)->putJson("/api/admin/pages/{$home->id}", [...$data, 'sections' => array_values(array_filter($data['sections'], fn ($s) => $s['id'] !== $removed))])->assertOk();

        $this->assertSoftDeleted(PageSection::withTrashed()->find($removed));
    }

    public function test_only_super_admins_open_the_trash(): void
    {
        $admin = User::factory()->create()->assignRole('admin');

        $this->asAdmin($admin)->getJson('/api/admin/trash')->assertForbidden();
        $this->asAdmin($admin)->postJson('/api/admin/trash/packages/1/restore')->assertForbidden();
        $this->asAdmin($this->superAdmin())->getJson('/api/admin/trash')->assertOk();
    }
}
