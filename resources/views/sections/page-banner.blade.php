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
/>
