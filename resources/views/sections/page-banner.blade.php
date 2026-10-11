@php($title = filled($c['title']) ? $c['title'] : $page->getTranslation('title', app()->getLocale()))
<x-site.block :style="$style" :section="$section" :container="false" @class([$first ? 'pt-20' : ''])>
    @if ($c['image'])
        <img src="{{ $c['image']->url('lg') }}" srcset="{{ $c['image']->srcset() }}" sizes="100vw" alt="" fetchpriority="high" class="absolute inset-0 -z-20 size-full object-cover">
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-brand-950/95 via-brand-950/80 to-brand-950/40" aria-hidden="true"></div>
    @elseif (! $style['image'])
        <div class="pattern-star absolute inset-0 -z-10 opacity-[0.06]" aria-hidden="true"></div>
        <div class="absolute -top-32 right-[-8%] -z-10 size-[460px] rounded-full bg-gold/12 blur-3xl" aria-hidden="true"></div>
    @endif

    <div class="container-site py-14 sm:py-20 lg:py-24">
        @if ($c['breadcrumbs'])
            <x-site.breadcrumbs :light="$style['tone'] === 'dark' || $c['image']" class="mb-6" />
        @endif
        <div class="max-w-3xl">
            @if (filled($c['eyebrow']))<p class="eyebrow">{{ $c['eyebrow'] }}</p>@endif
            <h1 @class(['sec-heading text-display-xl', 'mt-4' => filled($c['eyebrow'])])>{{ $title }}</h1>
            @if (filled($c['intro']))<p class="sec-text mt-5 max-w-2xl text-[1.0625rem] leading-relaxed sm:text-lg">{{ $c['intro'] }}</p>@endif
        </div>
    </div>
    <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-gold/50 to-transparent" aria-hidden="true"></div>
</x-site.block>
