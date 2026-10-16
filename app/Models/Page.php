<?php

namespace App\Models;

use App\Models\Concerns\Trashable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

#[Fillable([
    'type', 'system_key', 'title', 'slug', 'status', 'seo',
    'starts_at', 'expires_at', 'focused_layout', 'tracking', 'updated_by',
])]
class Page extends Model
{
    use HasTranslations, LogsActivity, Trashable;

    /** Freed while in the Trash, given back on restore. */
    protected array $releaseOnTrash = ['slug' => 'suffix'];

    public const TYPE_DEFAULT = 'default';

    public const TYPE_CUSTOM = 'custom';

    public const TYPE_CAMPAIGN = 'campaign';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    /** @var list<string> */
    public array $translatable = ['title'];

    protected function casts(): array
    {
        return [
            'seo' => 'array',
            'tracking' => 'array',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'focused_layout' => 'boolean',
        ];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(PageSection::class)->orderBy('sort');
    }

    public function visibleSections(): HasMany
    {
        return $this->sections()->where('is_visible', true);
    }

    public function isHome(): bool
    {
        return $this->system_key === 'home';
    }

    public function isCampaign(): bool
    {
        return $this->type === self::TYPE_CAMPAIGN;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * Campaign state for the admin: draft, scheduled, live or expired.
     */
    public function campaignState(): string
    {
        return match (true) {
            ! $this->isPublished() => 'draft',
            $this->starts_at?->isFuture() ?? false => 'scheduled',
            $this->expires_at?->isPast() ?? false => 'expired',
            default => 'live',
        };
    }

    public function isVisibleToPublic(): bool
    {
        return $this->isCampaign() ? $this->campaignState() === 'live' : $this->isPublished();
    }

    public function url(): string
    {
        return $this->isHome() ? url('/') : url($this->slug);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title', 'slug', 'status', 'starts_at', 'expires_at'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
