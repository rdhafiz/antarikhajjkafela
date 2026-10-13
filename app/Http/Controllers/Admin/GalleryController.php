<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\PaginatedList;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageSaveRequest;
use App\Models\GalleryAlbum;
use App\Models\GalleryItem;
use App\Models\Media;
use App\PageBuilder\ContentProcessor;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Gallery albums and the photos and videos inside them.
 */
class GalleryController extends Controller
{
    use PaginatedList;

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['published', 'draft'])],
            'content' => ['nullable', Rule::in(['photos', 'videos', 'empty'])],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'updated', 'title'])],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = GalleryAlbum::withCount([
            'items as images_count' => fn ($q) => $q->where('type', 'image'),
            'items as videos_count' => fn ($q) => $q->where('type', 'video'),
        ])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('is_published', $status === 'published'))
            ->when($filters['content'] ?? null, fn ($q, $content) => match ($content) {
                'photos' => $q->whereHas('items', fn ($q) => $q->where('type', 'image')),
                'videos' => $q->whereHas('items', fn ($q) => $q->where('type', 'video')),
                default => $q->whereDoesntHave('items'),
            })
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('slug', 'like', $this->likeTerm($search))
                ->orWhere('title', 'like', $this->likeTerm($search))))
            ->when($filters['sort'] ?? 'newest', fn ($q, $sort) => match ($sort) {
                'oldest' => $q->orderByRaw('event_date is null')->orderBy('event_date')->oldest('id'),
                'updated' => $q->latest('updated_at'),
                'title' => $q->orderBy('title->'.app(Locales::class)->default()),
                default => $q->newest(),
            })
            ->paginate($filters['per_page'] ?? 12);
        $albums = collect($page->items());

        // Cover fallback: the newest photo of each album, in one query.
        $firstImages = GalleryItem::where('type', 'image')
            ->whereIn('album_id', $albums->whereNull('cover_id')->pluck('id'))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->get(['album_id', 'media_id'])
            ->unique('album_id')
            ->pluck('media_id', 'album_id');

        $coverIds = $albums->mapWithKeys(fn (GalleryAlbum $a) => [$a->id => $a->cover_id ?? $firstImages[$a->id] ?? null]);
        $covers = Media::whereIn('id', $coverIds->filter())->get()->keyBy('id');

        // Counts for the filter drawer.
        $counts = [
            'published' => GalleryAlbum::where('is_published', true)->count(),
            'draft' => GalleryAlbum::where('is_published', false)->count(),
            'photos' => GalleryAlbum::whereHas('items', fn ($q) => $q->where('type', 'image'))->count(),
            'videos' => GalleryAlbum::whereHas('items', fn ($q) => $q->where('type', 'video'))->count(),
            'empty' => GalleryAlbum::whereDoesntHave('items')->count(),
        ];

        return $this->listResponse($page, fn (GalleryAlbum $a) => [
            ...$this->presentAlbum($a),
            'cover_thumb' => $covers->get((int) $coverIds[$a->id])?->url('thumb'),
            'images_count' => $a->images_count,
            'videos_count' => $a->videos_count,
        ], ['counts' => $counts]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedAlbum($request);
        $data['slug'] = $data['slug'] ?: $this->uniqueSlug($data['title']['en'] ?? reset($data['title']));

        $album = GalleryAlbum::create($data);
        Cache::forget('sitemap.xml');

        return response()->json(['data' => $this->presentAlbum($album)], 201);
    }

    public function show(GalleryAlbum $album): JsonResponse
    {
        return response()->json(['data' => [
            ...$this->presentAlbum($album),
            'items' => $album->items()->with('media')->get()->map(fn (GalleryItem $item) => $this->presentItem($item)),
        ]]);
    }

    public function update(Request $request, GalleryAlbum $album): JsonResponse
    {
        $data = $this->validatedAlbum($request, $album);
        $data['slug'] = $data['slug'] ?: $album->slug;

        $album->update($data);
        Cache::forget('sitemap.xml');

        return response()->json(['data' => $this->presentAlbum($album)]);
    }

    public function destroy(GalleryAlbum $album): JsonResponse
    {
        $album->delete();
        Cache::forget('sitemap.xml');

        return response()->json(status: 204);
    }

    /**
     * Add photos (media ids) or one YouTube video to an album.
     */
    public function addItems(Request $request, GalleryAlbum $album): JsonResponse
    {
        $data = $request->validate([
            'media_ids' => ['required_without:video_url', 'array', 'max:100'],
            'media_ids.*' => ['integer', 'exists:media,id'],
            'video_url' => ['required_without:media_ids', 'nullable', 'string', 'max:300', 'regex:'.ContentProcessor::YOUTUBE_PATTERN],
            'caption.*' => ['nullable', 'string', 'max:200'],
        ], ['video_url.regex' => 'Enter a YouTube video link.']);

        $caption = array_filter($data['caption'] ?? []);
        $items = collect();

        if (! empty($data['video_url'])) {
            $items->push($album->items()->create(['type' => 'video', 'video_url' => trim($data['video_url']), 'caption' => $caption]));
        }

        $existing = $album->items()->whereIn('media_id', $data['media_ids'] ?? [])->pluck('media_id')->all();

        foreach (array_unique(array_diff($data['media_ids'] ?? [], $existing)) as $mediaId) {
            $items->push($album->items()->create(['type' => 'image', 'media_id' => $mediaId, 'caption' => $caption]));
        }

        $album->touch();

        return response()->json(['data' => $items->map(fn (GalleryItem $item) => $this->presentItem($item->load('media')))], 201);
    }

    public function updateItem(Request $request, GalleryAlbum $album, GalleryItem $item): JsonResponse
    {
        abort_unless($item->album_id === $album->id, 404);

        $data = $request->validate(['caption.*' => ['nullable', 'string', 'max:200']]);
        $item->update(['caption' => array_filter($data['caption'] ?? [])]);

        return response()->json(['data' => $this->presentItem($item->load('media'))]);
    }

    /**
     * Remove items from the album. The photos stay in the media library.
     */
    public function removeItems(Request $request, GalleryAlbum $album): JsonResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        $album->items()->whereIn('id', $ids)->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAlbum(Request $request, ?GalleryAlbum $album = null): array
    {
        $default = app(Locales::class)->default();

        $data = $request->validate([
            'title' => ['required', 'array'],
            "title.{$default}" => ['required', 'string', 'max:120'],
            'title.*' => ['nullable', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:160', 'regex:'.PageSaveRequest::SLUG_PATTERN, Rule::unique('gallery_albums', 'slug')->ignore($album?->id)],
            'description.*' => ['nullable', 'string', 'max:600'],
            'cover_id' => ['nullable', 'integer', 'exists:media,id'],
            'event_date' => ['nullable', 'date'],
            'is_published' => ['boolean'],
        ], ['slug.regex' => 'Use lowercase letters, numbers and single hyphens only.']);

        return [
            'title' => array_filter($data['title']),
            'slug' => $data['slug'] ?? null,
            'description' => array_filter($data['description'] ?? []),
            'cover_id' => $data['cover_id'] ?? null,
            'event_date' => $data['event_date'] ?? null,
            'is_published' => (bool) ($data['is_published'] ?? true),
        ];
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title), 140, '') ?: 'album';
        $slug = $base;
        $i = 2;

        while (GalleryAlbum::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentAlbum(GalleryAlbum $album): array
    {
        return [
            'id' => $album->id,
            'title' => (object) $album->getTranslations('title'),
            'slug' => $album->slug,
            'url' => $album->url(),
            'description' => (object) $album->getTranslations('description'),
            'cover_id' => $album->cover_id,
            'event_date' => $album->event_date?->toDateString(),
            'is_published' => $album->is_published,
            'updated_at' => $album->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentItem(GalleryItem $item): array
    {
        return [
            'id' => $item->id,
            'type' => $item->type,
            'media_id' => $item->media_id,
            'thumb' => $item->type === 'image' ? $item->media?->url('thumb') : ($item->youtubeId() ? "https://i.ytimg.com/vi/{$item->youtubeId()}/mqdefault.jpg" : null),
            'video_url' => $item->video_url,
            'caption' => (object) $item->getTranslations('caption'),
            'created_at' => $item->created_at?->toIso8601String(),
        ];
    }
}
