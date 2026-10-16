<?php

namespace App\Domain\Blog;

use App\Http\Requests\Admin\PageSaveRequest;
use App\Models\Post;
use App\Models\SlugRedirect;
use App\Models\User;
use App\PageBuilder\ContentBlocks;
use App\Support\Locales;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Validation, saving and the admin payload for blog posts.
 */
class PostEditor
{
    public function __construct(
        private readonly ContentBlocks $blocks,
        private readonly Locales $locales,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function rules(Post $post): array
    {
        $default = $this->locales->default();

        return [
            'title' => ['required', 'array'],
            "title.{$default}" => ['required', 'string', 'max:180'],
            'title.*' => ['nullable', 'string', 'max:180'],
            'slug' => ['required', 'string', 'max:180', 'regex:'.PageSaveRequest::SLUG_PATTERN, Rule::unique('posts', 'slug')->ignore($post->id)],
            'excerpt.*' => ['nullable', 'string', 'max:400'],
            'category_id' => ['nullable', 'integer', 'exists:post_categories,id,deleted_at,NULL'],
            'banner_id' => ['nullable', 'integer', 'exists:media,id,deleted_at,NULL'],
            'author_name.*' => ['nullable', 'string', 'max:80'],
            'author_image_id' => ['nullable', 'integer', 'exists:media,id,deleted_at,NULL'],
            'tags' => ['nullable', 'array', 'max:12'],
            'tags.*' => ['string', 'max:40'],
            'status' => ['required', Rule::in([Post::STATUS_DRAFT, Post::STATUS_PUBLISHED])],
            'published_at' => ['nullable', 'date'],
            'is_featured' => ['boolean'],
            'seo.title.*' => ['nullable', 'string', 'max:70'],
            'seo.description.*' => ['nullable', 'string', 'max:200'],
            'seo.image' => ['nullable', 'integer', 'exists:media,id,deleted_at,NULL'],
            'seo.noindex' => ['nullable', 'boolean'],
            ...$this->blocks->rules('blocks', 60),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(Post $post, array $data, User $user): Post
    {
        DB::transaction(function () use ($post, $data, $user) {
            $oldSlug = $post->slug;
            $publishedAt = filled($data['published_at'] ?? null) ? Carbon::parse($data['published_at']) : null;

            // Publishing without a date publishes now; a future date schedules the post.
            if ($data['status'] === Post::STATUS_PUBLISHED && $publishedAt === null) {
                $publishedAt = now();
            }

            $post->fill([
                'title' => array_filter($data['title']),
                'slug' => $data['slug'],
                'excerpt' => array_filter($data['excerpt'] ?? []),
                'category_id' => $data['category_id'] ?? null,
                'banner_id' => $data['banner_id'] ?? null,
                'author_name' => array_filter($data['author_name'] ?? []),
                'author_image_id' => $data['author_image_id'] ?? null,
                'tags' => self::cleanTags($data['tags'] ?? []),
                'blocks' => $this->blocks->sanitize($data['blocks'] ?? []),
                'status' => $data['status'],
                'published_at' => $publishedAt,
                'is_featured' => (bool) ($data['is_featured'] ?? false),
                'seo' => [
                    'title' => array_filter($data['seo']['title'] ?? []),
                    'description' => array_filter($data['seo']['description'] ?? []),
                    'image' => $data['seo']['image'] ?? null,
                    'noindex' => (bool) ($data['seo']['noindex'] ?? false),
                ],
                'updated_by' => $user->id,
            ])->save();

            if ($oldSlug && $oldSlug !== $post->slug) {
                SlugRedirect::remember('post', $oldSlug, $post->id);
            }
        });

        Cache::forget('sitemap.xml');

        return $post->refresh();
    }

    public function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title), 150, '') ?: 'post';
        $slug = $base;
        $i = 2;

        while (Post::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    /**
     * Trimmed, de-duplicated tags (case-insensitive).
     *
     * @param  array<int, string>  $tags
     * @return list<string>
     */
    public static function cleanTags(array $tags): array
    {
        return collect($tags)
            ->map(fn ($tag) => trim(preg_replace('/\s+/u', ' ', (string) $tag)))
            ->filter()
            ->unique(fn ($tag) => mb_strtolower($tag))
            ->take(12)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(Post $post): array
    {
        return [
            'id' => $post->id,
            'title' => (object) $post->getTranslations('title'),
            'slug' => $post->slug,
            'url' => $post->url(),
            'excerpt' => (object) $post->getTranslations('excerpt'),
            'category_id' => $post->category_id,
            'banner_id' => $post->banner_id,
            'author_name' => (object) $post->getTranslations('author_name'),
            'author_image_id' => $post->author_image_id,
            'tags' => $post->tags ?? [],
            'blocks' => $post->blocks ?? [],
            'status' => $post->status,
            'state' => $post->state(),
            'published_at' => $post->published_at?->format('Y-m-d\TH:i'),
            'is_featured' => $post->is_featured,
            'seo' => [
                'title' => (object) ($post->seo['title'] ?? []),
                'description' => (object) ($post->seo['description'] ?? []),
                'image' => $post->seo['image'] ?? null,
                'noindex' => (bool) ($post->seo['noindex'] ?? false),
            ],
            'updated_at' => $post->updated_at?->toIso8601String(),
        ];
    }
}
