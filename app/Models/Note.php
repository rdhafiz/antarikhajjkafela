<?php

namespace App\Models;

use App\Models\Concerns\Trashable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A staff note on a booking or an inquiry: who wrote it and when. Notes are
 * added, not overwritten; a deleted note goes to the Trash like everything else.
 */
#[Fillable(['body', 'user_id'])]
class Note extends Model
{
    use Trashable;

    public function notable(): MorphTo
    {
        return $this->morphTo();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * @return array{id: int, body: string, author: string|null, created_at: string|null, can_delete: bool}
     */
    public function present(?User $viewer): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => $this->author?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'can_delete' => $viewer !== null && ($viewer->isSuperAdmin() || $viewer->id === $this->user_id),
        ];
    }
}
