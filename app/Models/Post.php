<?php

namespace App\Models;

use App\Models\Concerns\Trashable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

#[Fillable([
    'category_id', 'title', 'slug', 'excerpt', 'banner_id', 'author_name', 'author_image_id', 'tags',
    'blocks', 'status', 'published_at', 'is_featured', 'seo', 'updated_by',
])]
class Post extends Model
{
    use HasTranslations, LogsActivity, Trashable;

    /** Freed while in the Trash, given back on restore. */
    protected array $releaseOnTrash = ['slug' => 'suffix'];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    /** @var list<string> */
    public array $translatable = ['title', 'excerpt', 'author_name'];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'blocks' => 'array',
            'seo' => 'array',
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class, 'category_id');
    }

    public function banner(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'banner_id');
    }

    public function authorImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'author_image_id');
    }

    /**
     * Published and due: a future publish date keeps a post scheduled
     * without needing a background job.
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function state(): string
    {
        return match (true) {
            $this->status !== self::STATUS_PUBLISHED => 'draft',
            $this->published_at?->isFuture() ?? false => 'scheduled',
            default => 'published',
        };
    }

    public function isLive(): bool
    {
        return $this->state() === 'published';
    }

    public function url(): string
    {
        return route('blog.show', $this->slug);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title', 'slug', 'status', 'published_at'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
