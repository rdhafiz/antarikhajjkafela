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
        {{-- Decorative shapes behind the content, each drifting at its own speed (background only). --}}
        <div class="decor pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
            <span class="decor-blob" data-parallax="-0.12"></span>
            <span class="decor-ring" data-parallax="0.18"></span>
            <span class="decor-dots" data-parallax="0.08"></span>
            <span class="decor-dot" data-parallax="0.3"></span>
            <span class="decor-square" data-parallax="-0.22"></span>
            <svg class="decor-wave" data-parallax="0.24" viewBox="0 0 120 24" fill="none"><path d="M2 12c9.5-12 19.5-12 29 0s19.5 12 29 0 19.5-12 29 0 19.5 12 29 0" stroke="currentColor" stroke-width="4" stroke-linecap="round"/></svg>
            <svg class="decor-plus" data-parallax="-0.16" viewBox="0 0 24 24" fill="none"><path d="M12 3v18M3 12h18" stroke="currentColor" stroke-width="4" stroke-linecap="round"/></svg>
        </div>
    @endif

    @if ($container)
        <div class="container-site">{{ $slot }}</div>
    @else
        {{ $slot }}
    @endif
</section>
