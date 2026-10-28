<?php

namespace Tests\Feature\Admin;

use App\Domain\Media\MediaUploader;
use App\Models\GalleryAlbum;
use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MediaTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_upload_generates_webp_variants_and_keeps_the_original_private(): void
    {
        $admin = $this->superAdmin();

        $response = $this->asAdmin($admin)->post('/api/admin/media', [
            'file' => UploadedFile::fake()->image('kaaba.jpg', 2400, 1600),
            'usage' => 'card',
            'alt' => ['en' => 'The Kaaba', 'bn' => 'কাবা শরীফ'],
        ])->assertCreated();

        $media = Media::firstOrFail();

        $this->assertSame(['md', 'sm', 'admin'], array_keys($media->variants));
        $this->assertSame(800, $media->variants['md']['width']);
        $this->assertSame(600, $media->variants['md']['height']);
        $this->assertSame(['en' => 'The Kaaba', 'bn' => 'কাবা শরীফ'], $media->getTranslations('alt'));

        Storage::disk('public')->assertExists($media->variants['md']['path']);
        Storage::disk('public')->assertMissing("{$media->directory}/original.jpg");
        Storage::disk('local')->assertExists("{$media->directory}/original.jpg");

        $response->assertJsonPath('data.usage', 'card');
    }

    public function test_logo_margins_are_trimmed_and_older_logos_can_be_regenerated(): void
    {
        // A 600×200 white canvas with a 400×100 blue block in the middle.
        $canvas = imagecreatetruecolor(600, 200);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagefilledrectangle($canvas, 100, 50, 499, 149, imagecolorallocate($canvas, 20, 60, 200));
        $path = tempnam(sys_get_temp_dir(), 'logo').'.png';
        imagepng($canvas, $path);

        $this->asAdmin($this->superAdmin())->post('/api/admin/media', [
            'file' => new UploadedFile($path, 'logo.png', 'image/png', null, true),
            'usage' => 'logo',
        ])->assertCreated();

        $media = Media::firstOrFail();
        $this->assertSame([400, 100], [$media->variants['md']['width'], $media->variants['md']['height']]);

        // Regenerating writes new file names, so cached copies are not reused.
        $old = $media->variants['md']['path'];
        $this->assertTrue(app(MediaUploader::class)->regenerateVariants($media));
        $this->assertNotSame($old, $media->fresh()->variants['md']['path']);
        Storage::disk('public')->assertExists($media->fresh()->variants['md']['path']);

        // Other usages keep their margins.
        $this->asAdmin($this->superAdmin())->post('/api/admin/media', [
            'file' => new UploadedFile($path, 'photo.png', 'image/png', null, true),
            'usage' => 'general',
        ])->assertCreated();
        $this->assertSame([400, 133], [Media::latest('id')->first()->variants['admin']['width'], Media::latest('id')->first()->variants['admin']['height']]);
    }

    public function test_images_are_never_upscaled(): void
    {
        $this->asAdmin($this->superAdmin())->post('/api/admin/media', [
            'file' => UploadedFile::fake()->image('small.png', 300, 200),
            'usage' => 'hero',
        ])->assertCreated();

        $this->assertSame(300, Media::firstOrFail()->variants['xl']['width']);
    }

    public function test_manual_mode_applies_the_chosen_sizes_and_crop(): void
    {
        settings()->set('media', ['optimization_mode' => 'manual']);

        $this->asAdmin($this->superAdmin())->post('/api/admin/media', [
            'file' => UploadedFile::fake()->image('team.jpg', 2000, 1000),
            'usage' => 'avatar',
            'variants' => json_encode([
                'md' => ['width' => 200, 'height' => 200, 'fit' => 'cover', 'quality' => 75, 'crop' => ['x' => 100, 'y' => 0, 'width' => 1000, 'height' => 1000]],
            ]),
        ])->assertCreated();

        $variants = Media::firstOrFail()->variants;
        $this->assertSame([200, 200], [$variants['md']['width'], $variants['md']['height']]);
        $this->assertSame([96, 96], [$variants['sm']['width'], $variants['sm']['height']]);
    }

    public function test_overrides_are_ignored_in_automatic_mode(): void
    {
        $this->asAdmin($this->superAdmin())->post('/api/admin/media', [
            'file' => UploadedFile::fake()->image('team.jpg', 1000, 1000),
            'usage' => 'avatar',
            'variants' => json_encode(['md' => ['width' => 50, 'height' => 50, 'fit' => 'cover', 'quality' => 75]]),
        ])->assertCreated();

        $this->assertSame(320, Media::firstOrFail()->variants['md']['width']);
    }

    public function test_non_images_and_unknown_usages_are_rejected(): void
    {
        $admin = $this->superAdmin();

        $this->asAdmin($admin)->post('/api/admin/media', [
            'file' => UploadedFile::fake()->create('script.svg', 2, 'image/svg+xml'),
            'usage' => 'general',
        ])->assertJsonValidationErrors('file');

        $this->asAdmin($admin)->post('/api/admin/media', [
            'file' => UploadedFile::fake()->image('a.jpg'),
            'usage' => 'nonsense',
        ])->assertJsonValidationErrors('usage');
    }

    public function test_deleting_media_keeps_its_files_for_a_restore(): void
    {
        $admin = $this->superAdmin();
        $this->asAdmin($admin)->post('/api/admin/media', ['file' => UploadedFile::fake()->image('a.jpg', 800, 600), 'usage' => 'general']);
        $media = Media::firstOrFail();

        $this->asAdmin($admin)->deleteJson("/api/admin/media/{$media->id}")->assertNoContent();

        $this->assertSoftDeleted($media);
        Storage::disk('public')->assertExists($media->variants['lg']['path']);
        Storage::disk('local')->assertExists("{$media->directory}/original.jpg");
        $this->assertSame($admin->id, $media->fresh()->deleted_by);
    }

    public function test_media_in_use_cannot_be_deleted(): void
    {
        $admin = $this->superAdmin();
        $upload = fn (string $name) => Media::findOrFail($this->asAdmin($admin)->post('/api/admin/media', ['file' => UploadedFile::fake()->image($name, 800, 600), 'usage' => 'general'])->json('data.id'));
        $cover = $upload('cover.jpg');
        $logo = $upload('logo.jpg');
        $free = $upload('free.jpg');

        // #52: an album cover and the site logo are in use: refused, with where they are used.
        GalleryAlbum::create(['title' => ['en' => 'Umrah 2025'], 'slug' => 'umrah-2025', 'is_published' => true, 'cover_id' => $cover->id]);
        settings()->set('branding', ['logo' => $logo->id]);

        $this->asAdmin($admin)->getJson("/api/admin/media/{$cover->id}/used-in")->assertOk()->assertJsonPath('data', ['Album "Umrah 2025" (cover)']);
        $this->asAdmin($admin)->deleteJson("/api/admin/media/{$cover->id}")->assertStatus(409)->assertJsonPath('used_in.0', 'Album "Umrah 2025" (cover)');
        $this->asAdmin($admin)->deleteJson("/api/admin/media/{$logo->id}")->assertStatus(409)->assertJsonPath('used_in.0', 'Settings → Branding: Website logo');
        $this->assertNotSoftDeleted($cover);

        // Unused images go to the Trash as before.
        $this->asAdmin($admin)->getJson("/api/admin/media/{$free->id}/used-in")->assertJsonPath('data', []);
        $this->asAdmin($admin)->deleteJson("/api/admin/media/{$free->id}")->assertNoContent();

        // #53: deleted for good from the Trash, its files go too; an image in use can't be.
        $this->asAdmin($admin)->deleteJson("/api/admin/trash/media/{$free->id}")->assertNoContent();
        $this->assertNull(Media::withTrashed()->find($free->id));
        Storage::disk('public')->assertMissing($free->variants['lg']['path']);
        Storage::disk('local')->assertMissing("{$free->directory}/original.jpg");
    }

    public function test_media_permissions_are_enforced(): void
    {
        $viewer = $this->adminWith(['media.view']);

        $this->asAdmin($viewer)->getJson('/api/admin/media')->assertOk();
        $this->asAdmin($viewer)->post('/api/admin/media', ['file' => UploadedFile::fake()->image('a.jpg'), 'usage' => 'general'])->assertForbidden();
    }
}
