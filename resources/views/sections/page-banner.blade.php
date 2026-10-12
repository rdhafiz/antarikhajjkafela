@php($title = filled($c['title']) ? $c['title'] : $page->getTranslation('title', app()->getLocale()))
<x-site.block :style="$style" :section="$section" :container="false" :data-hero="$first ? true : null" @class(['pt-20 hero-half flex flex-col' => $first])>
    @if ($c['image'])
        <div class="hero-media" data-parallax-bg aria-hidden="true">
            <img src="{{ $c['image']->url('lg') }}" srcset="{{ $c['image']->srcset() }}" sizes="100vw" alt="" fetchpriority="high">
        </div>
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-brand-950/95 via-brand-950/80 to-brand-950/40" aria-hidden="true"></div>
    @elseif (! $style['image'])
        <div class="pattern-star absolute inset-0 -z-10 opacity-[0.06]" aria-hidden="true"></div>
        <div class="absolute -top-32 right-[-8%] -z-10 size-[460px] rounded-full bg-gold/12 blur-3xl" aria-hidden="true"></div>
    @endif

    <div @class(['container-site py-14 sm:py-20 lg:py-24', 'flex flex-1 flex-col justify-center' => $first])>
        <div class="hero-reveal" data-parallax-content>
            @if ($c['breadcrumbs'])
                <x-site.breadcrumbs :light="$style['tone'] === 'dark' || $c['image']" class="mb-6" />
            @endif
            @if (filled($c['eyebrow']))<p class="eyebrow">{{ $c['eyebrow'] }}</p>@endif
            <h1 @class(['sec-heading max-w-3xl text-display-xl', 'mt-4' => filled($c['eyebrow'])])>{{ $title }}</h1>
            @if (filled($c['intro']))<p class="sec-text mt-5 max-w-2xl text-[1.0625rem] leading-relaxed sm:text-lg">{{ $c['intro'] }}</p>@endif
        </div>
    </div>
    <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-gold/50 to-transparent" aria-hidden="true"></div>
</x-site.block>
