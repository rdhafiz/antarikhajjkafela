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

    /**
     * The submitted details as label-free text, for the admin, exports and emails:
     * nested values (the extra flights of a multi-city quote) become readable lines.
     *
     * @return array<string, string>
     */
    public function readableData(): array
    {
        return collect($this->data ?? [])->except('page')->map(function ($value, $key) {
            if ($key === 'more_flights' && is_array($value)) {
                return collect($value)->values()->map(fn ($leg, $i) => sprintf(
                    'Flight %d: %s → %s%s',
                    $i + 2,
                    $leg['from'] ?? '?',
                    $leg['to'] ?? '?',
                    filled($leg['date'] ?? null) ? ' · '.$leg['date'] : '',
                ))->implode("\n");
            }

            return is_array($value) ? implode(', ', array_map(fn ($v) => is_array($v) ? implode(' ', $v) : (string) $v, $value)) : (string) $value;
        })->all();
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
