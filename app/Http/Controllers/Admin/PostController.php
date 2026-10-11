<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Blog\PostEditor;
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
    public function __construct(private readonly PostEditor $editor) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'category_id' => ['nullable', 'integer'],
            'state' => ['nullable', Rule::in(['draft', 'scheduled', 'published'])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $posts = Post::query()
            ->with('category')
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when($filters['state'] ?? null, fn ($q, $state) => match ($state) {
                'draft' => $q->where('status', Post::STATUS_DRAFT),
                'scheduled' => $q->where('status', Post::STATUS_PUBLISHED)->where('published_at', '>', now()),
                default => $q->live(),
            })
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('slug', 'like', '%'.str($search)->slug().'%')
                ->orWhere('title', 'like', '%'.addcslashes($search, '%_').'%')))
            ->orderByRaw('published_at is null desc')
            ->latest('published_at')
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'data' => $posts->getCollection()->map(fn (Post $post) => [
                'id' => $post->id,
                'title' => (object) $post->getTranslations('title'),
                'slug' => $post->slug,
                'url' => $post->url(),
                'category' => $post->category ? (object) $post->category->getTranslations('name') : null,
                'state' => $post->state(),
                'is_featured' => $post->is_featured,
                'banner_id' => $post->banner_id,
                'published_at' => $post->published_at?->toIso8601String(),
                'updated_at' => $post->updated_at?->toIso8601String(),
            ]),
            'meta' => ['current_page' => $posts->currentPage(), 'last_page' => $posts->lastPage(), 'total' => $posts->total(), 'per_page' => $posts->perPage()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $default = app(Locales::class)->default();

        $data = $request->validate([
            'title' => ['required', 'array'],
            "title.{$default}" => ['required', 'string', 'max:180'],
            'title.*' => ['nullable', 'string', 'max:180'],
            'category_id' => ['nullable', 'integer', 'exists:post_categories,id'],
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
