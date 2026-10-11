<?php

namespace App\Domain\Blog;

use App\Models\Page;
use App\Models\Post;
use App\Models\PostCategory;

/**
 * Posts on the Blog page, filterable by category (?category=) and tag (?tag=).
 */
class BlogListing
{
    public const PER_PAGE = 9;

    public function view(): string
    {
        return 'site.blog.listing';
    }

    /**
     * @return array<string, mixed>
     */
    public function data(Page $page): array
    {
        $request = request();
        $categories = PostCategory::whereHas('posts', fn ($q) => $q->live())->orderBy('sort')->get();
        $category = $categories->firstWhere('slug', $request->query('category'));
        $tag = is_string($request->query('tag')) ? mb_substr(trim($request->query('tag')), 0, 40) : null;

        $posts = Post::live()
            ->with('banner', 'category')
            ->when($category, fn ($q) => $q->where('category_id', $category->id))
            ->when($tag, fn ($q) => $q->whereJsonContains('tags', $tag))
            ->latest('published_at')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withPath($page->url())
            ->withQueryString();

        // The first page without filters leads with the newest featured post.
        $lead = null;
        if (! $category && ! $tag && $posts->currentPage() === 1) {
            $lead = $posts->getCollection()->firstWhere('is_featured', true) ?? $posts->getCollection()->first();
        }

        return [
            'posts' => $posts,
            'lead' => $lead,
            'categories' => $categories,
            'category' => $category,
            'tag' => $tag,
            'blogUrl' => $page->url(),
        ];
    }
}
