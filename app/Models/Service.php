<?php

namespace App\Models;

use App\Models\Concerns\Trashable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

#[Fillable(['name', 'slug', 'summary', 'headline', 'highlight', 'description', 'icon', 'image_id', 'is_active', 'travel_date', 'sort', 'seo'])]
class Service extends Model
{
    use HasTranslations, LogsActivity, Trashable;

    /** Freed while in the Trash, given back on restore. */
    protected array $releaseOnTrash = ['slug' => 'suffix'];

    /** @var list<string> */
    public array $translatable = ['name', 'summary', 'headline', 'highlight', 'description'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort' => 'integer',
            'seo' => 'array',
        ];
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * On the public site: active and with at least one published package
     * (a service with nothing to book stays hidden until it has one).
     */
    public function scopeListed(Builder $query): Builder
    {
        return $query->active()->whereHas('packages', fn ($q) => $q->where('status', Package::STATUS_PUBLISHED));
    }

    /**
     * Services with one of these slugs, including trashed ones (whose slug is
     * set aside until they are restored).
     *
     * @param  list<string>  $slugs
     */
    public static function everHad(array $slugs): bool
    {
        return static::withTrashed()
            ->where(fn (Builder $q) => $q->whereIn('slug', $slugs)->orWhereIn('trashed_values->slug', $slugs))
            ->exists();
    }

    /**
     * Hajj and Umrah, as opposed to the agency's other services such as tours.
     */
    public function isPilgrimage(): bool
    {
        return in_array($this->slug, ['hajj', 'umrah'], true);
    }

    public function url(): string
    {
        return url($this->slug);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'slug', 'is_active'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
