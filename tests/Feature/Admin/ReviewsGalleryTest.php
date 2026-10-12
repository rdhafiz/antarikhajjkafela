<?php

namespace Tests\Feature\Admin;

use App\Models\GalleryAlbum;
use App\Models\Media;
use App\Models\Page;
use App\Models\Review;
use Database\Seeders\BlogSeeder;
use Database\Seeders\PageSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ReviewsGalleryTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PageSeeder::class, BlogSeeder::class]);
    }

    public function test_reviews_are_managed_and_shown(): void
    {
        $admin = $this->adminWith(['reviews.view', 'reviews.create', 'reviews.update']);

        $this->asAdmin($admin)->postJson('/api/admin/reviews', ['name' => ['en' => 'Empty']])
            ->assertUnprocessable()->assertJsonValidationErrors('text');

        $this->asAdmin($admin)->postJson('/api/admin/reviews', [
            'name' => ['en' => 'Abdul Karim', 'bn' => 'আব্দুল করিম'],
            'intro' => ['en' => 'Hajj 2025 pilgrim'],
            'text' => ['en' => 'Every step was well organised.'],
            'rating' => 5,
            'is_featured' => true,
        ])->assertCreated();

        $video = $this->asAdmin($admin)->postJson('/api/admin/reviews', [
            'name' => ['en' => 'Rahima Begum'],
            'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
        ])->assertCreated()->json('data.id');

        $this->asAdmin($admin)->postJson('/api/admin/reviews', ['name' => ['en' => 'Bad link'], 'video_url' => 'https://vimeo.com/1'])
            ->assertJsonValidationErrors('video_url');

        $this->get('/reviews')
            ->assertOk()
            ->assertSeeText('Every step was well organised.')
            ->assertSeeText('Rahima Begum')
            ->assertSee('i.ytimg.com/vi/dQw4w9WgXcQ', false);

        // The home page testimonials section shows featured reviews first.
        $this->get('/')->assertOk()->assertSeeText('Abdul Karim');

        Review::whereKey($video)->update(['is_published' => false]);
        $this->get('/reviews')->assertDontSeeText('Rahima Begum');

        $this->asAdmin($this->adminWith(['reviews.view']))->deleteJson("/api/admin/reviews/{$video}")->assertForbidden();
    }

    public function test_albums_with_photos_and_videos(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $admin = $this->superAdmin();

        $media = $this->asAdmin($admin)->post('/api/admin/media', [
            'file' => UploadedFile::fake()->image('arafat.jpg', 1200, 800),
            'usage' => 'gallery',
        ])->assertCreated()->json('data.id');

        $albumId = $this->asAdmin($admin)->postJson('/api/admin/gallery', [
            'title' => ['en' => 'Hajj 2025: Arafat', 'bn' => 'হজ ২০২৫: আরাফাত'],
            'event_date' => '2025-06-05',
        ])->assertCreated()->assertJsonPath('data.slug', 'hajj-2025-arafat')->json('data.id');

        // Empty albums are not public.
        $this->get('/gallery/hajj-2025-arafat')->assertNotFound();

        $this->asAdmin($admin)->postJson("/api/admin/gallery/{$albumId}/items", ['media_ids' => [$media, $media]])
            ->assertCreated()->assertJsonCount(1, 'data');
        $this->asAdmin($admin)->postJson("/api/admin/gallery/{$albumId}/items", ['media_ids' => [$media]])
            ->assertCreated()->assertJsonCount(0, 'data');
        $videoItem = $this->asAdmin($admin)->postJson("/api/admin/gallery/{$albumId}/items", ['video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'caption' => ['en' => 'Day of Arafat']])
            ->assertCreated()->json('data.0.id');

        $this->get('/gallery')->assertOk()->assertSeeText('Hajj 2025: Arafat');
        $this->get('/gallery/hajj-2025-arafat')
            ->assertOk()
            ->assertSee(Media::find($media)->url('thumb'), false)
            ->assertSee('dQw4w9WgXcQ', false)
            ->assertSee('Day of Arafat', false);
        $this->get('/')->assertOk()->assertSee(Media::find($media)->url('lg'), false);
        $this->get('/sitemap.xml')->assertSee(url('/gallery/hajj-2025-arafat'), false);

        $this->asAdmin($admin)->postJson("/api/admin/gallery/{$albumId}/items/remove", ['ids' => [$videoItem]])->assertNoContent();
        $this->assertSame(1, GalleryAlbum::find($albumId)->items()->count());

        $this->asAdmin($admin)->putJson("/api/admin/gallery/{$albumId}", ['title' => ['en' => 'Arafat'], 'slug' => 'arafat', 'is_published' => false])->assertOk();
        $this->get('/gallery/arafat')->assertNotFound();

        $this->asAdmin($admin)->deleteJson("/api/admin/gallery/{$albumId}")->assertNoContent();
        $this->assertNotNull(Media::find($media), 'Photos stay in the media library.');
    }

    public function test_home_gallery_shows_photos_for_one_album_and_cards_for_several(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $admin = $this->superAdmin();
        $upload = fn () => $this->asAdmin($admin)->post('/api/admin/media', ['file' => UploadedFile::fake()->image('p.jpg', 900, 700), 'usage' => 'gallery'])->json('data.id');

        $first = GalleryAlbum::create(['title' => ['en' => 'Arafat day'], 'slug' => 'arafat-day', 'event_date' => '2025-06-05']);
        $first->items()->create(['type' => 'image', 'media_id' => $upload()]);

        // One album: its photos fill the section.
        $this->get('/')->assertOk()->assertSee('href="'.url('/gallery/arafat-day').'"', false)->assertDontSee('Madinah ziyarah');

        $second = GalleryAlbum::create(['title' => ['en' => 'Madinah ziyarah'], 'slug' => 'madinah-ziyarah', 'event_date' => '2025-07-01']);
        $second->items()->create(['type' => 'image', 'media_id' => $upload()]);

        // Several albums: the albums are shown, newest first.
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('Madinah ziyarah', $html);
        $this->assertStringContainsString('Arafat day', $html);
        $this->assertLessThan(strpos($html, 'Arafat day'), strpos($html, 'Madinah ziyarah'));
    }

    public function test_listing_pages_keep_their_listing_section(): void
    {
        foreach (['blog', 'reviews', 'gallery'] as $key) {
            $page = Page::where('system_key', $key)->firstOrFail();
            $this->assertTrue($page->sections()->where('component', 'page_listing')->exists());
            $this->get($page->url())->assertOk();
        }
    }
}
