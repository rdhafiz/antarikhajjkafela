<?php

namespace App\Models;

use App\Models\Concerns\Trashable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['source', 'page_id', 'page_section_id', 'name', 'phone', 'email', 'data', 'status', 'notes', 'locale', 'ip', 'read_at'])]
class Inquiry extends Model
{
    use Trashable;

    public const STATUSES = ['new', 'in_progress', 'closed'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
