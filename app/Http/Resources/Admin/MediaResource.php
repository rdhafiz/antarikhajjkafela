<?php

namespace App\Http\Resources\Admin;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Media
 */
class MediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $disk = Storage::disk($this->disk);

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'usage' => $this->usage,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'alt' => $this->getTranslations('alt'),
            'url' => $this->url(),
            'thumb_url' => $this->url('admin'),
            'variants' => collect($this->variants ?? [])->map(fn ($v) => [
                'url' => $disk->url($v['path']),
                'width' => $v['width'],
                'height' => $v['height'],
                'size' => $v['size'],
            ]),
            'optimized_size' => collect($this->variants ?? [])->except('admin')->sum('size'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
