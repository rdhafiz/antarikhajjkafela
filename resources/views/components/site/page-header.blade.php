@props([
    'title',
    'intro' => null,
    'eyebrow' => null,
    'image' => null,        // optional App\Models\Media background
])
{{-- Banner at the top of inner pages: title, intro and breadcrumbs. Without an image, the default banner from the settings is used. --}}
@php($image ??= app(App\Support\Branding::class)->defaultBanner())
<section class="hero-half relative isolate flex flex-col overflow-hidden bg-brand-950 pt-20 text-white" data-hero>
    @if ($image)
        <div class="hero-media" data-parallax-bg aria-hidden="true">
            <img src="{{ $image->url('lg') }}" srcset="{{ $image->srcset() }}" sizes="100vw" alt="" fetchpriority="high">
        </div>
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-brand-950/95 via-brand-950/80 to-brand-950/45" aria-hidden="true"></div>
    @else
        <div class="pattern-star absolute inset-0 -z-10 opacity-[0.06]" aria-hidden="true"></div>
        <div class="absolute -top-32 right-[-8%] -z-10 size-[460px] rounded-full bg-gold/12 blur-3xl" aria-hidden="true"></div>
    @endif

    <div class="container-site flex flex-1 flex-col justify-center py-14 sm:py-20 lg:py-24">
        <div class="hero-reveal max-w-3xl" data-parallax-content>
            <x-site.breadcrumbs light class="mb-6" />
            @if ($eyebrow)
                <p class="eyebrow eyebrow-light">{{ $eyebrow }}</p>
            @endif
            <h1 @class(['text-display-xl text-white', 'mt-4' => $eyebrow])>{{ $title }}</h1>
            @if ($intro)
                <p class="mt-5 max-w-2xl text-[1.0625rem] leading-relaxed text-white/75 sm:text-lg">{{ $intro }}</p>
            @endif
            @if ($slot->isNotEmpty())
                <div>{{ $slot }}</div>
            @endif
        </div>
    </div>
    <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-gold/50 to-transparent" aria-hidden="true"></div>
</section>
