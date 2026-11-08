@php($title = filled($c['title']) ? $c['title'] : $page->getTranslation('title', app()->getLocale()))
{{-- Inner-page banner: the shared hero (centered, 75vh at the top of the page). --}}
<x-site.hero
    :title="$title"
    :eyebrow="$c['eyebrow']"
    :highlight="$c['highlight'] ?? null"
    :intro="$c['intro']"
    :image="$c['image'] ?? $style['image']"
    :top="$first"
    :id="$style['id']"
    data-section="page_banner"
>
    {{-- Campaign pages: the section's button, under the text, aligned as chosen in the admin. --}}
    @if (filled($style['button']['link']['url'] ?? null) && filled($style['button']['link']['label'] ?? null))
        <div @class([
            'flex w-full',
            'justify-start' => $style['button']['align'] === 'left',
            'justify-end' => $style['button']['align'] === 'right',
            'justify-center' => ! in_array($style['button']['align'], ['left', 'right'], true),
        ])>
            <x-site.link-button :link="$style['button']['link']" variant="gold" size="lg" icon-right="arrow-right" />
        </div>
    @endif
</x-site.hero>
