<?php

namespace App\Models;

use App\Models\Concerns\Trashable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

#[Fillable(['title', 'slug', 'description', 'cover_id', 'event_date', 'is_published'])]
class GalleryAlbum extends Model
{
    use HasTranslations, Trashable;

    /** Freed while in the Trash, given back on restore. */
    protected array $releaseOnTrash = ['slug' => 'suffix'];

    /** @var list<string> */
    public array $translatable = ['title', 'description'];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'is_published' => 'boolean',
        ];
    }

    /**
     * Items, newest first.
     */
    public function items(): HasMany
    {
        return $this->hasMany(GalleryItem::class, 'album_id')->latest('created_at')->latest('id');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Newest albums first (by event date, then creation).
     */
    public function scopeNewest(Builder $query): Builder
    {
        return $query->orderByDesc('event_date')->latest('id');
    }

    public function url(): string
    {
        return route('gallery.show', $this->slug);
    }
}
