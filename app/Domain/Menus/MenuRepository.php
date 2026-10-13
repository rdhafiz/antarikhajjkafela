<?php

namespace App\Domain\Menus;

use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Menus as rendered on the public website: visible items only, labels in
 * the current language, links resolved to URLs. Cached until a menu is saved.
 *
 * The cache keeps site links as paths ("/hajj") and adds the domain on each
 * request, so a cache built under another host (a CLI run, a copied
 * database or storage folder) never sends visitors to that host.
 */
class MenuRepository
{
    public function __construct(private readonly MenuLinks $links) {}

    /**
     * @return list<array{label: string, url: string|null, new_tab: bool, children: list<array<string, mixed>>}>
     */
    public function tree(string $location): array
    {
        $locale = app()->getLocale();

        try {
            $items = Cache::rememberForever($this->cacheKey($location), fn () => $this->load($location));
        } catch (Throwable) {
            return [];
        }

        return $this->localize($items, $locale);
    }

    public function flush(string $location): void
    {
        Cache::forget($this->cacheKey($location));
    }

    /**
     * Forget every cached menu, e.g. after a linked page, service or package changed.
     */
    public function flushAll(): void
    {
        foreach (array_keys(config('menus.locations')) as $location) {
            $this->flush($location);
        }
    }

    /**
     * Visible items with all translations, cached once for every language.
     *
     * @return list<array<string, mixed>>
     */
    private function load(string $location): array
    {
        $menu = Menu::where('location', $location)->first();

        if (! $menu) {
            return [];
        }

        $items = $menu->items()->where('is_visible', true)->get();

        return $this->branch($items->groupBy('parent_id'), null);
    }

    /**
     * @param  Collection<string|int, Collection<int, MenuItem>>  $byParent
     * @return list<array<string, mixed>>
     */
    private function branch(Collection $byParent, ?int $parentId): array
    {
        return $byParent->get($parentId ?? '', collect())
            ->map(fn (MenuItem $item) => [
                'label' => $item->getTranslations('label'),
                'url' => $this->toPath($this->links->resolve($item->link_type, $item->link_target, $item->url)),
                'new_tab' => $item->open_in_new_tab,
                'children' => $this->branch($byParent, $item->id),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function localize(array $items, string $locale): array
    {
        $fallback = config('app.fallback_locale');

        return array_map(fn ($item) => [
            'label' => $item['label'][$locale] ?? $item['label'][$fallback] ?? collect($item['label'])->first(),
            'url' => $this->toUrl($item['url']),
            'new_tab' => $item['new_tab'],
            'children' => $this->localize($item['children'], $locale),
        ], $items);
    }

    /**
     * Strip this site's own root from a URL; other sites stay as they are.
     */
    private function toPath(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $root = rtrim(url('/'), '/');

        if ($url === $root) {
            return '/';
        }

        foreach (['/', '?', '#'] as $next) {
            if (str_starts_with($url, $root.$next)) {
                return ($next === '/' ? '' : '/').substr($url, strlen($root));
            }
        }

        return $url;
    }

    private function toUrl(?string $url): ?string
    {
        return $url !== null && str_starts_with($url, '/') && ! str_starts_with($url, '//') ? url($url) : $url;
    }

    /** "v2": entries from before paths were cached held full URLs. */
    private function cacheKey(string $location): string
    {
        return "menus.v2.{$location}";
    }
}
