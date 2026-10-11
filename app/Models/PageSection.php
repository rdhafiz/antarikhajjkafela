<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['page_id', 'component', 'sort', 'is_visible', 'content', 'style'])]
class PageSection extends Model
{
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'style' => 'array',
            'is_visible' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
