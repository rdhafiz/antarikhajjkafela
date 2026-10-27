<?php

namespace App\Models;

use App\Models\Concerns\Trashable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Spatie\Translatable\HasTranslations;

#[Fillable([
    'uuid', 'disk', 'directory', 'filename', 'original_name', 'mime_type', 'extension',
    'size', 'width', 'height', 'usage', 'alt', 'variants', 'uploaded_by',
])]
class Media extends Model
{
    use HasTranslations, Trashable;

    /** @var list<string> */
    public array $translatable = ['alt'];

    protected function casts(): array
    {
        return [
            'variants' => 'array',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Public URL of a variant. Without a name, the largest variant is used.
     */
    /**
     * Text for the image's alt attribute: its own description in the page
     * language (set in the media library), else the fallback, usually the
     * title shown with the image.
     */
    public function altText(?string $fallback = null): string
    {
        $alt = trim((string) $this->getTranslation('alt', app()->getLocale(), false));

        return $alt !== '' ? $alt : trim((string) $fallback);
    }

    public function url(?string $variant = null): ?string
    {
        $data = $variant !== null
            ? ($this->variants[$variant] ?? null)
            : $this->largestVariant();

        $data ??= $this->largestVariant();

        return $data ? Storage::disk($this->disk)->url($data['path']) : null;
    }

    /**
     * A srcset string built from all variants of this image's usage.
     */
    public function srcset(): string
    {
        return collect($this->variants ?? [])
            ->except('admin')
            ->filter(fn ($v) => ! empty($v['width']))
            ->sortBy('width')
            ->map(fn ($v) => Storage::disk($this->disk)->url($v['path']).' '.$v['width'].'w')
            ->implode(', ');
    }

    /**
     * @return array{path: string, width: int, height: int, size: int}|null
     */
    private function largestVariant(): ?array
    {
        return collect($this->variants ?? [])
            ->except('admin')
            ->sortByDesc('width')
            ->first()
            ?? ($this->variants['admin'] ?? null);
    }
}
