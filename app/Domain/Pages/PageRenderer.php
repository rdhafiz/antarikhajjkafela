<?php

namespace App\Domain\Pages;

use App\Models\Media;
use App\Models\Page;
use App\Models\PageSection;
use App\PageBuilder\ComponentRegistry;
use App\PageBuilder\ContentProcessor;
use App\PageBuilder\SectionStyle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns a page's visible sections into HTML for the current language.
 * All images used by the page are loaded in a single query.
 */
class PageRenderer
{
    public function __construct(
        private readonly ComponentRegistry $components,
        private readonly ContentProcessor $content,
    ) {}

    /**
     * @return array{html: string, schema: list<array<string, mixed>>, dark_top: bool, ends_with_cta: bool}
     */
    public function render(Page $page): array
    {
        $sections = $page->visibleSections()->get()
            ->filter(fn (PageSection $section) => $this->components->has($section->component))
            ->values();

        $media = $this->loadMedia($sections);
        $locale = app()->getLocale();

        $html = '';
        $schema = [];
        $darkTop = false;

        foreach ($sections as $index => $section) {
            $component = $this->components->get($section->component);
            $content = $this->content->localize($component->fields(), $section->content, $locale, $media);
            $style = SectionStyle::resolve($section->style ?? [], $component->defaultStyle(), $media);

            if ($index === 0) {
                $darkTop = $style['tone'] === 'dark';
            }

            try {
                $html .= view($component->view(), [
                    'c' => $content,
                    'section' => $section,
                    'page' => $page,
                    'style' => $style,
                    'first' => $index === 0,
                    ...$component->data($content, $section, $page),
                ])->render();
            } catch (Throwable $e) {
                // One broken section must not take the whole page down.
                report($e);
                Log::warning("Page section {$section->id} ({$section->component}) failed to render.");
            }

            if ($contribution = $component->schema($content, $page)) {
                $schema[] = $contribution;
            }
        }

        return [
            'html' => $html,
            'schema' => $schema,
            'dark_top' => $darkTop,
            'ends_with_cta' => in_array($sections->last()?->component, ['cta', 'lead_form', 'contact_form'], true),
        ];
    }

    /**
     * @param  Collection<int, PageSection>  $sections
     * @return Collection<int, Media>
     */
    private function loadMedia(Collection $sections): Collection
    {
        $ids = [];

        foreach ($sections as $section) {
            $component = $this->components->get($section->component);
            array_push($ids, ...$this->content->mediaIds($component->fields(), $section->content));

            if (! empty($section->style['background_image'])) {
                $ids[] = (int) $section->style['background_image'];
            }
        }

        return $ids === [] ? collect() : Media::whereIn('id', array_unique($ids))->get()->keyBy('id');
    }
}
