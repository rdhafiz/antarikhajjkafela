<?php

namespace App\Domain\Pages;

use App\Models\Page;
use App\Models\User;
use App\PageBuilder\ComponentRegistry;
use App\PageBuilder\ContentProcessor;
use App\PageBuilder\SectionStyle;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Creates and saves pages and their sections from validated admin input.
 */
class PageEditor
{
    public function __construct(
        private readonly ComponentRegistry $components,
        private readonly ContentProcessor $content,
    ) {}

    /**
     * Create a custom or campaign page with a sensible starting layout.
     *
     * @param  array{type: string, title: array<string, string|null>, slug: string}  $data
     */
    public function create(array $data, User $user): Page
    {
        return DB::transaction(function () use ($data, $user) {
            $page = Page::create([
                'type' => $data['type'],
                'title' => array_filter($data['title']),
                'slug' => $data['slug'],
                'status' => Page::STATUS_DRAFT,
                'seo' => [],
                'updated_by' => $user->id,
            ]);

            $starter = $data['type'] === Page::TYPE_CAMPAIGN
                ? ['hero', 'lead_form', 'faq', 'cta']
                : ['page_banner', 'rich_text'];

            foreach ($starter as $sort => $key) {
                $component = $this->components->get($key);
                $page->sections()->create([
                    'component' => $key,
                    'sort' => $sort,
                    'content' => $this->content->sanitize($component->fields(), $component->defaults()),
                    'style' => SectionStyle::sanitize([]),
                ]);
            }

            $this->flushCaches();

            return $page;
        });
    }

    /**
     * Save page details and replace its sections with the submitted list.
     *
     * @param  array<string, mixed>  $data  validated input
     */
    public function save(Page $page, array $data, User $user): Page
    {
        DB::transaction(function () use ($page, $data, $user) {
            $attributes = [
                'title' => array_filter($data['title'] ?? []),
                'status' => $data['status'],
                'seo' => $this->seo($data['seo'] ?? []),
                'updated_by' => $user->id,
            ];

            if ($page->type !== Page::TYPE_DEFAULT) {
                $attributes['slug'] = $data['slug'];
            }

            if ($page->isCampaign()) {
                $attributes += [
                    'starts_at' => $data['starts_at'] ?? null,
                    'expires_at' => $data['expires_at'] ?? null,
                    'focused_layout' => (bool) ($data['focused_layout'] ?? false),
                    'tracking' => array_filter(Arr::only($data['tracking'] ?? [], ['gtm_id', 'facebook_pixel_id'])),
                ];
            }

            $page->update($attributes);

            $this->syncSections($page, $data['sections'] ?? []);
        });

        $this->flushCaches();

        return $page->refresh();
    }

    /**
     * Copy a custom or campaign page (as a draft) with all its sections.
     */
    public function duplicate(Page $page, User $user): Page
    {
        return DB::transaction(function () use ($page, $user) {
            $copy = $page->replicate(['system_key']);
            $copy->slug = $this->uniqueSlug($page->slug.'-copy');
            $copy->status = Page::STATUS_DRAFT;
            $copy->setTranslations('title', collect($page->getTranslations('title'))->map(fn ($t) => "{$t} (copy)")->all());
            $copy->updated_by = $user->id;
            $copy->save();

            foreach ($page->sections as $section) {
                $copy->sections()->create($section->only(['component', 'sort', 'is_visible', 'content', 'style']));
            }

            return $copy;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     */
    private function syncSections(Page $page, array $sections): void
    {
        $kept = [];

        foreach (array_values($sections) as $sort => $input) {
            $component = $this->components->get($input['component']);

            $attributes = [
                'component' => $component->key(),
                'sort' => $sort,
                'is_visible' => (bool) ($input['is_visible'] ?? true),
                'content' => $this->content->sanitize($component->fields(), $input['content'] ?? []),
                'style' => SectionStyle::sanitize($input['style'] ?? []),
            ];

            $section = isset($input['id']) ? $page->sections()->whereKey($input['id'])->first() : null;

            if ($section) {
                // A section never changes into another component.
                $section->update(Arr::except($attributes, 'component'));
            } else {
                $section = $page->sections()->create($attributes);
            }

            $kept[] = $section->id;
        }

        $page->sections()->whereNotIn('id', $kept)->get()->each->delete();
    }

    /**
     * @param  array<string, mixed>  $seo
     * @return array<string, mixed>
     */
    private function seo(array $seo): array
    {
        return [
            'title' => array_filter($seo['title'] ?? []),
            'description' => array_filter($seo['description'] ?? []),
            'image' => ! empty($seo['image']) ? (int) $seo['image'] : null,
            'noindex' => (bool) ($seo['noindex'] ?? false),
            'schema' => filled($seo['schema'] ?? null) ? trim($seo['schema']) : null,
        ];
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $i = 2;

        while (Page::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    private function flushCaches(): void
    {
        Cache::forget('sitemap.xml');
    }
}
