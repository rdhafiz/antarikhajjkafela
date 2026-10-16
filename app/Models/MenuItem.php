<?php

namespace App\Models;

use App\Models\Concerns\Trashable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

#[Fillable(['menu_id', 'parent_id', 'sort', 'label', 'link_type', 'link_target', 'url', 'open_in_new_tab', 'is_visible'])]
class MenuItem extends Model
{
    use HasTranslations, Trashable;

    /** @var list<string> */
    public array $translatable = ['label'];

    protected function casts(): array
    {
        return [
            'open_in_new_tab' => 'boolean',
            'is_visible' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort');
    }
}
