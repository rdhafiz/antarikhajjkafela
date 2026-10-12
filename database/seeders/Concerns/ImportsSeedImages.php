<?php

namespace Database\Seeders\Concerns;

use App\Domain\Media\MediaUploader;
use App\Models\Media;
use App\Models\PageSection;
use Illuminate\Http\UploadedFile;

/**
 * Imports images bundled in database/seeders/images into the media library
 * once (matched by file name and usage), and fills only empty image slots so
 * choices made in the admin portal are never replaced.
 */
trait ImportsSeedImages
{
    /**
     * @param  string  $path  relative to database/seeders/images
     * @param  array{0: string, 1: string}|null  $alt  English and Bangla alt text
     */
    protected function importImage(string $path, string $usage, ?array $alt = null): Media
    {
        $name = basename($path);
        $existing = Media::where('original_name', $name)->where('usage', $usage)->first();

        if ($existing) {
            return $existing;
        }

        $copy = tempnam(sys_get_temp_dir(), 'seed');
        copy(dirname(__DIR__).'/images/'.$path, $copy);

        try {
            $media = app(MediaUploader::class)->upload(new UploadedFile($copy, $name, 'image/webp', null, true), $usage);
        } finally {
            @unlink($copy);
        }

        if ($alt) {
            $media->update(['alt' => ['en' => $alt[0], 'bn' => $alt[1]]]);
        }

        return $media;
    }

    /**
     * Set a section's image field when it is still empty.
     *
     * @param  callable(): Media  $media
     * @param  array<string, mixed>  $extra  other content to set at the same time
     */
    protected function fillSectionImage(PageSection $section, string $field, callable $media, array $extra = []): bool
    {
        $content = $section->content ?? [];

        if (! empty($content[$field])) {
            return false;
        }

        $section->update(['content' => [...$content, ...$extra, $field => $media()->id]]);

        return true;
    }
}
