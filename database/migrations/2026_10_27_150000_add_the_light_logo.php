<?php

use App\Domain\Media\MediaUploader;
use App\Models\Media;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Http\UploadedFile;

/**
 * Over dark page tops the header now shows a light version of the logo
 * instead of a white panel. A site that has the Antarik logo uploaded gets
 * the bundled light version as its "Logo for dark backgrounds"; a site
 * without an uploaded logo already uses the bundled one.
 */
return new class extends Migration
{
    public function up(): void
    {
        $logo = settings('branding.logo') ? Media::find(settings('branding.logo')) : null;

        if (! $logo || settings('branding.logo_light') || ! str_contains(strtolower($logo->original_name), 'antarik')) {
            return;
        }

        $copy = tempnam(sys_get_temp_dir(), 'logo');
        copy(public_path('images/brand/logo-light-880.webp'), $copy);

        try {
            $media = app(MediaUploader::class)->upload(new UploadedFile($copy, 'antarik-logo-light.webp', 'image/webp', null, true), 'logo');
        } finally {
            @unlink($copy);
        }

        $media->update(['alt' => ['en' => 'Antarik Travel International', 'bn' => 'আন্তরিক ট্রাভেল ইন্টারন্যাশনাল']]);
        settings()->set('branding', ['logo_light' => $media->id]);
    }

    public function down(): void
    {
        // The light logo stays; it can be changed or removed in Settings → Branding.
    }
};
