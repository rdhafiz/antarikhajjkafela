<?php

namespace App\Domain\Blog;

use App\Models\Media;
use App\Models\Post;
use App\Models\Service;
use App\PageBuilder\ContentBlocks;
use App\PageBuilder\ContentProcessor;
use App\Support\Branding;
use App\Support\Dates;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Blog post data for the public pages, in the current language.
 */
class PostPresenter
{
    public function __construct(
        private readonly ContentBlocks $blocks,
        private readonly ContentProcessor $content,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function detail(Post $post): array
    {
        $locale = app()->getLocale();
        $ids = $this->blocks->mediaIds($post->blocks);
        $media = $ids === [] ? collect() : Media::whereIn('id', array_unique($ids))->get()->keyBy('id');
        $blocks = $this->blocks->localize($post->blocks, $locale, $media, $this->content);

        return [
            'title' => $post->getTranslation('title', $locale),
            'excerpt' => $post->getTranslation('excerpt', $locale),
            'author' => $post->getTranslation('author_name', $locale) ?: setting_t('branding.site_name'),
            'blocks' => $blocks,
            'minutes' => self::readingMinutes($blocks),
            'date' => self::date($post->published_at),
            'cta' => $this->bookingCta($post),
        ];
    }

    /**
     * Posts tagged Hajj or Umrah invite the reader to book (free) with the
     * matching service, when it is on the website and has packages.
     *
     * @return array{kind: string, url: string}|null
     */
    public function bookingCta(Post $post): ?array
    {
        $tags = mb_strtolower(implode(' ', $post->tags ?? []));
        $kind = str_contains($tags, 'hajj') ? 'hajj' : (str_contains($tags, 'umrah') ? 'umrah' : null);

        if (! $kind) {
            return null;
        }

        $service = Service::query()
            ->where('is_active', true)
            ->where('travel_date', $kind === 'hajj' ? 'hajj_year' : 'month_year')
            ->whereHas('packages', fn ($q) => $q->published())
            ->orderBy('sort')
            ->first();

        return $service ? ['kind' => $kind, 'url' => $service->url().'#packages'] : null;
    }

    /**
     * Roughly 200 words a minute, counted from text, lists and tables.
     *
     * @param  list<array<string, mixed>>  $blocks
     */
    public static function readingMinutes(array $blocks): int
    {
        $text = collect($blocks)->map(fn ($b) => match ($b['type']) {
            'text' => strip_tags(str_replace('<', ' <', $b['body'])),
            'list' => implode(' ', $b['items']),
            'table' => implode(' ', array_merge(...$b['rows'] ?: [[]])),
            default => '',
        })->implode(' ');

        $words = count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY));

        return max(1, (int) ceil($words / 200));
    }

    /**
     * Reading time for cards, without loading the post's images.
     */
    public function minutes(Post $post): int
    {
        return self::readingMinutes($this->blocks->localize($post->blocks, app()->getLocale(), collect(), $this->content));
    }

    public static function date(?Carbon $date): ?string
    {
        return Dates::format($date);
    }

    /**
     * JSON-LD for a post.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function schema(Post $post, array $data): array
    {
        return array_filter([
            '@type' => 'BlogPosting',
            'headline' => $data['title'],
            'description' => $data['excerpt'] ?: null,
            'image' => $post->banner?->url('lg'),
            'datePublished' => $post->published_at?->toIso8601String(),
            'dateModified' => $post->updated_at?->toIso8601String(),
            'author' => ['@type' => 'Person', 'name' => $data['author']],
            'publisher' => ['@id' => url('/#organization')],
            'mainEntityOfPage' => $post->url(),
            'inLanguage' => app()->getLocale(),
            'articleSection' => $post->category?->getTranslation('name', app()->getLocale()),
            'keywords' => $post->tags ? implode(', ', $post->tags) : null,
        ]);
    }

    /**
     * Up to $limit live posts sharing the category or tags, newest first,
     * topped up with the latest posts.
     *
     * @return Collection<int, Post>
     */
    public function related(Post $post, int $limit = 3): Collection
    {
        $tags = collect($post->tags ?? [])->map(fn ($t) => mb_strtolower($t));

        $candidates = Post::live()
            ->with('banner', 'category')
            ->whereKeyNot($post->id)
            ->latest('published_at')
            ->limit(30)
            ->get();

        return $candidates
            ->sortByDesc(fn (Post $p) => ($p->category_id && $p->category_id === $post->category_id ? 2 : 0)
                + collect($p->tags ?? [])->map(fn ($t) => mb_strtolower($t))->intersect($tags)->count())
            ->take($limit)
            ->values();
    }

    /**
     * Author photo, or the agency icon for posts without one.
     */
    public static function authorImage(Post $post): string
    {
        return $post->authorImage?->url('sm') ?? app(Branding::class)->iconUrl(192);
    }
}
