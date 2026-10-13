<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\PageBuilder\ContentProcessor;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Review::with('photo')->orderBy('sort')->latest('id')->get()->map(fn (Review $r) => $this->present($r))]);
    }

    public function store(Request $request): JsonResponse
    {
        Review::query()->increment('sort');
        $review = Review::create($this->validated($request) + ['sort' => 0]);

        return response()->json(['data' => $this->present($review)], 201);
    }

    public function update(Request $request, Review $review): JsonResponse
    {
        $review->update($this->validated($request));

        return response()->json(['data' => $this->present($review)]);
    }

    public function destroy(Review $review): JsonResponse
    {
        $review->delete();

        return response()->json(status: 204);
    }

    public function reorder(Request $request): JsonResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'exists:reviews,id']])['ids'];

        foreach (array_values($ids) as $sort => $id) {
            Review::whereKey($id)->update(['sort' => $sort]);
        }

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $default = app(Locales::class)->default();

        $data = $request->validate([
            'name' => ['required', 'array'],
            "name.{$default}" => ['required', 'string', 'max:80'],
            'name.*' => ['nullable', 'string', 'max:80'],
            'intro.*' => ['nullable', 'string', 'max:120'],
            'text.*' => ['nullable', 'string', 'max:2000'],
            'video_url' => ['nullable', 'string', 'max:300', 'regex:'.ContentProcessor::YOUTUBE_PATTERN],
            'photo_id' => ['nullable', 'integer', 'exists:media,id'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'is_featured' => ['boolean'],
            'is_published' => ['boolean'],
        ], ['video_url.regex' => 'Enter a YouTube video link.']);

        $text = array_filter(array_map(fn ($t) => filled($t) ? trim($t) : null, $data['text'] ?? []));

        if ($text === [] && blank($data['video_url'] ?? null)) {
            throw ValidationException::withMessages(['text' => 'Add the review text, a YouTube video, or both.']);
        }

        return [
            'name' => array_filter($data['name']),
            'intro' => array_filter($data['intro'] ?? []),
            'text' => $text,
            'video_url' => filled($data['video_url'] ?? null) ? trim($data['video_url']) : null,
            'photo_id' => $data['photo_id'] ?? null,
            'rating' => $data['rating'] ?? null,
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'is_published' => (bool) ($data['is_published'] ?? true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Review $review): array
    {
        return [
            'id' => $review->id,
            'name' => (object) $review->getTranslations('name'),
            'intro' => (object) $review->getTranslations('intro'),
            'text' => (object) $review->getTranslations('text'),
            'video_url' => $review->video_url,
            'photo_id' => $review->photo_id,
            'photo_url' => $review->photo_id ? $review->photo?->url('sm') : null,
            'rating' => $review->rating,
            'is_featured' => $review->is_featured,
            'is_published' => $review->is_published,
        ];
    }
}
