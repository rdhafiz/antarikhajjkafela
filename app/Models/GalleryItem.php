<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

#[Fillable(['album_id', 'type', 'media_id', 'video_url', 'caption'])]
class GalleryItem extends Model
{
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['caption'];

    public function album(): BelongsTo
    {
        return $this->belongsTo(GalleryAlbum::class, 'album_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function youtubeId(): ?string
    {
        return $this->video_url && preg_match('#(?:v=|embed/|shorts/|youtu\.be/)([\w-]{6,})#', $this->video_url, $m) ? $m[1] : null;
    }
}
