<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MediaUploadRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Manual-mode options arrive as a JSON string alongside the file.
        if (is_string($this->input('variants'))) {
            $this->merge(['variants' => json_decode($this->input('variants'), true)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $max = (int) config('media.max_dimension');

        return [
            'file' => [
                'required',
                'file',
                'mimetypes:'.implode(',', config('media.mimes')),
                'max:'.config('media.max_upload_kb'),
                "dimensions:max_width={$max},max_height={$max}",
            ],
            'usage' => ['required', 'string', Rule::in(array_keys(config('media.usages')))],
            'alt' => ['nullable', 'array'],
            'alt.*' => ['nullable', 'string', 'max:255'],
            'variants' => ['nullable', 'array'],
            'variants.*.width' => ['nullable', 'integer', 'min:16', "max:{$max}"],
            'variants.*.height' => ['nullable', 'integer', 'min:16', "max:{$max}"],
            'variants.*.fit' => ['nullable', Rule::in(['scale', 'cover'])],
            'variants.*.quality' => ['nullable', 'integer', 'min:30', 'max:100'],
            'variants.*.crop' => ['nullable', 'array'],
            'variants.*.crop.x' => ['required_with:variants.*.crop', 'integer', 'min:0'],
            'variants.*.crop.y' => ['required_with:variants.*.crop', 'integer', 'min:0'],
            'variants.*.crop.width' => ['required_with:variants.*.crop', 'integer', 'min:1'],
            'variants.*.crop.height' => ['required_with:variants.*.crop', 'integer', 'min:1'],
        ];
    }
}
