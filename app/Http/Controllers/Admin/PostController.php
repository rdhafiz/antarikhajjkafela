<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Blog\PostEditor;
use App\Http\Controllers\Admin\Concerns\PaginatedList;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostCategory;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    use PaginatedList;

    public function __construct(private readonly PostEditor $editor) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'category_id' => ['nullable', 'integer'],
            'state' => ['nullable', Rule::in(['draft', 'scheduled', 'published'])],
            'featured' => ['nullable', Rule::in(['yes', 'no'])],
            'sort' => ['nullable', Rule::in(['newest', 'updated', 'title'])],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $posts = Post::query()
            ->with(['category', 'banner'])
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when($filters['state'] ?? null, fn ($q, $state) => match ($state) {
                'draft' => $q->where('status', Post::STATUS_DRAFT),
                'scheduled' => $q->where('status', Post::STATUS_PUBLISHED)->where('published_at', '>', now()),
                default => $q->live(),
            })
            ->when($filters['featured'] ?? null, fn ($q, $featured) => $q->where('is_featured', $featured === 'yes'))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('slug', 'like', '%'.str($search)->slug().'%')
                ->orWhere('title', 'like', '%'.addcslashes($search, '%_').'%')))
            ->when($filters['sort'] ?? 'newest', fn ($q, $sort) => match ($sort) {
                'updated' => $q->latest('updated_at'),
                'title' => $q->orderBy('title->'.app(Locales::class)->default()),
                default => $q->orderByRaw('published_at is null desc')->latest('published_at'),
            })
            ->latest('id')
            ->paginate($filters['per_page'] ?? 20);

        // Counts for the filter drawer.
        $now = now();
        $counts = [
            'states' => [
                'published' => Post::query()->live()->count(),
                'scheduled' => Post::where('status', Post::STATUS_PUBLISHED)->where('published_at', '>', $now)->count(),
                'draft' => Post::where('status', Post::STATUS_DRAFT)->count(),
            ],
            'categories' => Post::query()->toBase()->selectRaw('category_id, count(*) as n')->groupBy('category_id')->pluck('n', 'category_id')->map(fn ($n) => (int) $n),
            'featured' => Post::where('is_featured', true)->count(),
        ];

        return $this->listResponse($posts, fn (Post $post) => [
            'id' => $post->id,
            'title' => (object) $post->getTranslations('title'),
            'slug' => $post->slug,
            'url' => $post->url(),
            'category' => $post->category ? (object) $post->category->getTranslations('name') : null,
            'state' => $post->state(),
            'is_featured' => $post->is_featured,
            'banner_id' => $post->banner_id,
            'banner_url' => $post->banner?->url('admin'),
            'excerpt' => (object) $post->getTranslations('excerpt'),
            'author_name' => (object) $post->getTranslations('author_name'),
            'tags' => $post->tags ?? [],
            'published_at' => $post->published_at?->toIso8601String(),
            'updated_at' => $post->updated_at?->toIso8601String(),
        ], ['counts' => $counts]);
    }

    public function store(Request $request): JsonResponse
    {
        $default = app(Locales::class)->default();

        $data = $request->validate([
            'title' => ['required', 'array'],
            "title.{$default}" => ['required', 'string', 'max:180'],
            'title.*' => ['nullable', 'string', 'max:180'],
            'category_id' => ['nullable', 'integer', 'exists:post_categories,id,deleted_at,NULL'],
        ]);

        $post = $this->editor->save(new Post, [
            'title' => $data['title'],
            'slug' => $this->editor->uniqueSlug($data['title']['en'] ?? $data['title'][$default]),
            'category_id' => $data['category_id'] ?? null,
            'author_name' => (array) settings('branding.site_name'),
            'status' => Post::STATUS_DRAFT,
        ], $request->user());

        return response()->json(['data' => PostEditor::present($post)], 201);
    }

    public function show(Post $post): JsonResponse
    {
        return response()->json(['data' => PostEditor::present($post)]);
    }

    public function update(Request $request, Post $post): JsonResponse
    {
        $data = $request->validate($this->editor->rules($post), [
            'slug.regex' => 'Use lowercase letters, numbers and single hyphens only.',
        ]);

        return response()->json(['data' => PostEditor::present($this->editor->save($post, $data, $request->user()))]);
    }

    public function destroy(Post $post): JsonResponse
    {
        $post->delete();
        Cache::forget('sitemap.xml');

        return response()->json(status: 204);
    }

    /**
     * A short-lived signed link to preview a draft or scheduled post.
     */
    public function previewUrl(Post $post): JsonResponse
    {
        return response()->json(['url' => URL::temporarySignedRoute('posts.preview', now()->addHour(), $post)]);
    }

    /**
     * Categories and the tags already in use, for the editor.
     */
    public function meta(): JsonResponse
    {
        return response()->json([
            'categories' => PostCategory::withCount('posts')->orderBy('sort')->get()->map(fn (PostCategory $c) => [
                'id' => $c->id,
                'name' => (object) $c->getTranslations('name'),
                'slug' => $c->slug,
                'posts_count' => $c->posts_count,
            ]),
            'tags' => Post::pluck('tags')->flatten()->filter()->unique(fn ($t) => mb_strtolower($t))->sort()->values(),
        ]);
    }
}
