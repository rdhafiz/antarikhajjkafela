<?php

use App\Domain\Media\MediaUploader;
use App\Models\Media;
use Illuminate\Database\Migrations\Migration;

/**
 * Logos are now trimmed of plain margins on upload, so the header can show
 * them larger. Logos uploaded before get their variants written again.
 */
return new class extends Migration
{
    public function up(): void
    {
        $uploader = app(MediaUploader::class);

        Media::where('usage', 'logo')->get()->each(fn (Media $media) => $uploader->regenerateVariants($media));
    }

    public function down(): void
    {
        // The trimmed variants stay; the originals are untouched.
    }
};
