<?php

namespace App\Models;

use App\Models\Concerns\Trashable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

#[Fillable(['name', 'intro', 'text', 'video_url', 'photo_id', 'rating', 'is_featured', 'is_published', 'sort'])]
class Review extends Model
{
    use HasTranslations, Trashable;

    /** @var list<string> */
    public array $translatable = ['name', 'intro', 'text'];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'photo_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('is_featured')->orderBy('sort')->latest('id');
    }

    public function youtubeId(): ?string
    {
        return $this->video_url && preg_match('#(?:v=|embed/|shorts/|youtu\.be/)([\w-]{6,})#', $this->video_url, $m) ? $m[1] : null;
    }
}
