@props([
    'style',                // resolved SectionStyle
    'section' => null,
    'container' => true,    // wrap the content in .container-site
])
{{-- Wrapper for every page-builder section: background, image + overlay, text palette, spacing, anchor. --}}
<section
    @if ($style['id']) id="{{ $style['id'] }}" @endif
    @if ($section) data-section="{{ $section->component }}" @endif
    {{ $attributes->class(['relative isolate overflow-hidden', $style['class']]) }}
    @if ($style['style']) style="{{ $style['style'] }}" @endif
>
    @if ($style['image'])
        <div class="absolute inset-x-0 -inset-y-[20%] -z-20" data-parallax="0.15" aria-hidden="true">
            <img
                src="{{ $style['image']->url('lg') }}"
                srcset="{{ $style['image']->srcset() }}"
                sizes="100vw"
                alt=""
                loading="lazy"
                decoding="async"
                class="size-full object-cover"
            >
        </div>
        <div class="absolute inset-0 -z-10" style="{{ $style['overlay'] }}" aria-hidden="true"></div>
    @endif

    @if (($style['decor'] ?? 'none') === 'shapes')
        <x-site.decor />
    @endif

    @php
        // Campaign pages: the section's button, at the bottom with the alignment chosen in the admin.
        $button = $style['button'] ?? null;
        $showButton = filled($button['link']['url'] ?? null) && filled($button['link']['label'] ?? null);
    @endphp

    @if ($container)
        <div class="container-site">
            {{ $slot }}
            @if ($showButton)
                <x-site.section-button :button="$button" :dark="$style['tone'] === 'dark'" />
            @endif
        </div>
    @else
        {{ $slot }}
        @if ($showButton)
            <div class="container-site"><x-site.section-button :button="$button" :dark="$style['tone'] === 'dark'" /></div>
        @endif
    @endif
</section>
