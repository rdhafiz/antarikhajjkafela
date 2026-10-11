<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

#[Fillable([
    'service_id', 'name', 'slug', 'summary', 'banner_id', 'booking_starts_on', 'booking_ends_on',
    'duration_days', 'status', 'is_featured', 'sort', 'advance_type', 'advance_value', 'min_price',
    'flights', 'sections', 'seo', 'updated_by',
])]
class Package extends Model
{
    use HasTranslations, LogsActivity;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const UNITS = ['person' => 'Per person', 'room' => 'Per room', 'group' => 'Per group'];

    public const ADVANCE_TYPES = ['none' => 'No advance', 'fixed' => 'Fixed amount per traveler', 'percentage' => 'Percentage of the total'];

    /** @var list<string> */
    public array $translatable = ['name', 'summary'];

    protected function casts(): array
    {
        return [
            'booking_starts_on' => 'date',
            'booking_ends_on' => 'date',
            'duration_days' => 'integer',
            'is_featured' => 'boolean',
            'advance_value' => 'decimal:2',
            'min_price' => 'decimal:2',
            'flights' => 'array',
            'sections' => 'array',
            'seo' => 'array',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function banner(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'banner_id');
    }

    public function types(): HasMany
    {
        return $this->hasMany(PackageType::class)->orderBy('sort');
    }

    public function prices(): HasManyThrough
    {
        return $this->hasManyThrough(PackagePrice::class, PackageType::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->whereHas('service', fn ($q) => $q->where('is_active', true));
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * Booking window state: upcoming (not open yet), open, or closed.
     */
    public function bookingState(): string
    {
        $today = now()->startOfDay();

        return match (true) {
            $this->booking_starts_on !== null && $this->booking_starts_on->gt($today) => 'upcoming',
            $this->booking_ends_on !== null && $this->booking_ends_on->lt($today) => 'closed',
            default => 'open',
        };
    }

    public function isBookable(): bool
    {
        return $this->isPublished() && $this->bookingState() === 'open';
    }

    /**
     * Advance due for one traveler at the given price, or null when none is required.
     */
    public function advanceFor(float $price, int $travelers): ?float
    {
        return match ($this->advance_type) {
            'fixed' => round((float) $this->advance_value * $travelers, 2),
            'percentage' => round($price * $travelers * (float) $this->advance_value / 100, 2),
            default => null,
        };
    }

    public function url(): string
    {
        return route('packages.show', $this->slug);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'slug', 'status', 'min_price', 'booking_starts_on', 'booking_ends_on'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
