<?php

namespace App\Models;

use App\Models\Concerns\Trashable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * A pricing variant of a package type (e.g. "4/5-bed shared room"), with
 * its own hotel details.
 */
#[Fillable(['package_type_id', 'title', 'short_description', 'price', 'unit', 'hotels', 'sort'])]
class PackagePrice extends Model
{
    use HasTranslations, Trashable;

    /** @var list<string> */
    public array $translatable = ['title', 'short_description'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'hotels' => 'array',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(PackageType::class, 'package_type_id');
    }
}
