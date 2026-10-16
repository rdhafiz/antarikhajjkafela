<?php

namespace App\Models;

use App\Models\Concerns\Trashable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

#[Fillable(['package_id', 'code', 'name', 'short_description', 'sort'])]
class PackageType extends Model
{
    use HasTranslations, Trashable;

    /** @var list<string> */
    public array $translatable = ['name', 'short_description'];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(PackagePrice::class)->orderBy('sort');
    }
}
