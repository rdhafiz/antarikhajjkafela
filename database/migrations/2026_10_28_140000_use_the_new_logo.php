<?php

use App\Domain\Media\MediaUploader;
use App\Models\Media;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Http\UploadedFile;

/**
 * The client's new Antarik Travel International logo (October 2026). The
 * bundled files already carry it; a site that uploaded the previous Antarik
 * logo, light logo, app icon or favicon gets the new one in its place. A
 * custom logo of another name is left alone. The old images stay in the
 * media library.
 */
return new class extends Migration
{
    private const FILES = [
        'logo' => ['images/brand/logo-880.webp', 'antarik-travel-international-logo.webp', 'logo'],
        'logo_light' => ['images/brand/logo-light-880.webp', 'antarik-travel-international-logo-light.webp', 'logo'],
        'app_icon' => ['images/brand/icon-512.webp', 'antarik-travel-international-icon.webp', 'icon'],
        'favicon' => ['images/brand/icon-512.webp', 'antarik-travel-international-icon.webp', 'icon'],
    ];

    public function up(): void
    {
        $uploaded = [];

        foreach (self::FILES as $key => [$file, $name, $usage]) {
            $current = settings("branding.{$key}") ? Media::find(settings("branding.{$key}")) : null;

            if (! $current || ! str_contains(strtolower($current->original_name), 'antarik')) {
                continue;
            }

            // The app icon and favicon share one upload.
            $uploaded[$file] ??= $this->upload($file, $name, $usage);
            settings()->set('branding', [$key => $uploaded[$file]->id]);
        }
    }

    public function down(): void
    {
        // The new logo stays; it can be changed in Settings → Branding.
    }

    private function upload(string $file, string $name, string $usage): Media
    {
        $copy = tempnam(sys_get_temp_dir(), 'logo');
        copy(public_path($file), $copy);

        try {
            $media = app(MediaUploader::class)->upload(new UploadedFile($copy, $name, 'image/webp', null, true), $usage);
        } finally {
            @unlink($copy);
        }

        $media->update(['alt' => ['en' => 'Antarik Travel International', 'bn' => 'আন্তরিক ট্রাভেল ইন্টারন্যাশনাল']]);

        return $media;
    }
};
