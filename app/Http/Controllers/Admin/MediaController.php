<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Media\MediaUploader;
use App\Domain\Media\MediaUsage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MediaUploadRequest;
use App\Http\Resources\Admin\MediaResource;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class MediaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'usage' => ['nullable', Rule::in(array_keys(config('media.usages')))],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'largest', 'smallest', 'name'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $media = Media::query()
            ->when($request->string('search')->trim()->value(), function ($q, string $search) {
                // "!" escapes % and _ so they are matched literally (the same in MySQL and SQLite).
                $q->whereRaw("original_name like ? escape '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%']);
            })
            ->when($request->input('usage'), fn ($q, string $usage) => $q->where('usage', $usage))
            ->when($request->input('sort', 'newest'), fn ($q, string $sort) => match ($sort) {
                'oldest' => $q->oldest('id'),
                'largest' => $q->orderByDesc('size'),
                'smallest' => $q->orderBy('size'),
                'name' => $q->orderBy('original_name'),
                default => $q,
            })
            ->latest('id')
            ->paginate($request->integer('per_page', 24));

        return MediaResource::collection($media)->additional([
            // Counts for the filter drawer.
            'counts' => [
                'usages' => Media::query()->toBase()->selectRaw('`usage`, count(*) as n')->groupBy('usage')->pluck('n', 'usage')->map(fn ($n) => (int) $n),
                'total' => Media::count(),
            ],
        ]);
    }

    public function store(MediaUploadRequest $request, MediaUploader $uploader): MediaResource
    {
        $manual = settings('media.optimization_mode') === 'manual';

        $media = $uploader->upload(
            $request->file('file'),
            $request->input('usage'),
            $manual ? $request->input('variants') : null,
            $request->user(),
        );

        if ($request->filled('alt')) {
            $media->setTranslations('alt', array_filter($request->input('alt')))->save();
        }

        return new MediaResource($media);
    }

    public function show(Media $media): MediaResource
    {
        return new MediaResource($media);
    }

    public function update(Request $request, Media $media): MediaResource
    {
        $data = $request->validate([
            'alt' => ['nullable', 'array'],
            'alt.*' => ['nullable', 'string', 'max:255'],
        ]);

        $media->setTranslations('alt', array_filter($data['alt'] ?? []))->save();

        return new MediaResource($media);
    }

    /**
     * Where the image is used (the admin asks before offering to delete it).
     */
    public function usedIn(Media $media, MediaUsage $usage): JsonResponse
    {
        return response()->json(['data' => $usage->of($media)]);
    }

    /**
     * Images still in use are not deleted: their pages would show a broken image.
     */
    public function destroy(Media $media, MediaUsage $usage): JsonResponse
    {
        if ($usedIn = $usage->of($media)) {
            return response()->json([
                'message' => 'This image is still used, so it cannot be deleted. Replace it there first: '.implode('; ', $usedIn).'.',
                'used_in' => $usedIn,
            ], 409);
        }

        $media->delete();

        return response()->json(status: 204);
    }

    /**
     * The usages and their variant presets, used by the upload and manual optimization UI.
     */
    public function usages(): JsonResponse
    {
        $usages = collect(config('media.usages'))->map(fn ($usage, $key) => [
            'key' => $key,
            'label' => $usage['label'],
            'variants' => collect($usage['variants'])->map(fn ($v, $name) => [
                'name' => (string) $name,
                'width' => $v['width'],
                'height' => $v['height'],
                'fit' => $v['fit'],
                'quality' => $v['quality'] ?? config('media.default_quality'),
            ])->values(),
        ])->values();

        return response()->json([
            'data' => $usages,
            'mode' => settings('media.optimization_mode'),
            'max_upload_kb' => config('media.max_upload_kb'),
            'mimes' => config('media.mimes'),
        ]);
    }
}
