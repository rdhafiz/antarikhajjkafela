<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Nothing is deleted for good: delete() only moves a record to the Trash,
 * noting who did it. Unique values listed in $releaseOnTrash (a slug, email
 * or name) are set aside while the record is in the Trash, so a new record
 * may use them, and given back on restore: a slug or name gets a number if
 * it was taken in the meantime; an email that is taken blocks the restore.
 *
 * Models using it may declare:
 *   protected array $releaseOnTrash = ['slug' => 'suffix'];   // or 'unique'
 *
 * @mixin Model
 */
trait Trashable
{
    use SoftDeletes;

    public static function bootTrashable(): void
    {
        static::softDeleted(function (self $model) {
            $values = [];

            foreach (array_keys($model->releaseRules()) as $column) {
                $original = $model->getAttribute($column);
                if ($original !== null && $original !== '') {
                    $values[$column] = $original;
                    $model->setAttribute($column, $model->setAside((string) $original));
                }
            }

            $model->forceFill(array_filter([
                'deleted_by' => auth()->id(),
                'trashed_values' => $values === [] ? null : $values,
            ], fn ($value) => $value !== null))->saveQuietly();
        });

        static::restoring(function (self $model) {
            foreach ($model->releaseRules() as $column => $rule) {
                $original = $model->trashed_values[$column] ?? null;
                if ($original === null) {
                    continue;
                }

                if ($model->isTaken($column, $original)) {
                    if ($rule === 'unique') {
                        throw ValidationException::withMessages([$column => "Another record now uses \"{$original}\". Change it there first, then restore."]);
                    }
                    $original = $model->nextFree($column, $original);
                }

                $model->setAttribute($column, $original);
            }

            $model->forceFill(['deleted_by' => null] + ($model->releaseRules() === [] ? [] : ['trashed_values' => null]));
        });
    }

    public function initializeTrashable(): void
    {
        if ($this->releaseRules() !== []) {
            $this->mergeCasts(['trashed_values' => 'array']);
        }
    }

    /**
     * @return array<string, 'suffix'|'unique'>
     */
    public function releaseRules(): array
    {
        return property_exists($this, 'releaseOnTrash') ? $this->releaseOnTrash : [];
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by')->withTrashed();
    }

    /**
     * The value kept while in the Trash: unique through the record's id.
     */
    private function setAside(string $value): string
    {
        return Str::limit("deleted-{$this->getKey()}-{$value}", 150, '');
    }

    private function isTaken(string $column, string $value): bool
    {
        return static::query()->whereKeyNot($this->getKey())->where($column, $value)->exists();
    }

    private function nextFree(string $column, string $value): string
    {
        $n = 2;
        while ($this->isTaken($column, "{$value}-{$n}")) {
            $n++;
        }

        return "{$value}-{$n}";
    }
}
