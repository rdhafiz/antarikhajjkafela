<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['type', 'old_slug', 'target_id'])]
class SlugRedirect extends Model
{
    /**
     * Remember an old slug, so visitors following old links are redirected.
     */
    public static function remember(string $type, string $oldSlug, int $targetId): void
    {
        static::updateOrCreate(['type' => $type, 'old_slug' => $oldSlug], ['target_id' => $targetId]);
    }

    public static function target(string $type, string $slug): ?int
    {
        return static::where('type', $type)->where('old_slug', $slug)->value('target_id');
    }
}
