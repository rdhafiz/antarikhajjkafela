@props(['link', 'variant' => 'primary', 'size' => 'md', 'icon' => null, 'iconRight' => null])
{{-- Renders a page-builder link field (['label', 'url', 'external']) as a button. --}}
@if ($link && filled($link['url'] ?? null) && filled($link['label'] ?? null))
    <x-site.button
        :href="$link['url']"
        :variant="$variant"
        :size="$size"
        :icon="$icon"
        :icon-right="$iconRight"
        :external="$link['external'] ?? false"
        {{ $attributes }}
    >{{ $link['label'] }}</x-site.button>
@endif
