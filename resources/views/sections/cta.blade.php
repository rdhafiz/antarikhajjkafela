@inject('site', App\Support\SiteContext::class)
@php
    $variant = $c['variant'] ?? 'banner';
    $phones = $site->phones();
    $whatsapp = $site->whatsappUrl();
    $dark = $style['tone'] === 'dark';
@endphp
<x-site.block :style="$style" :section="$section">
    @if ($variant === 'minimal')
        <div class="flex flex-col items-center gap-8 text-center lg:flex-row lg:justify-between lg:text-left" data-reveal>
            <div class="max-w-2xl">
                @if (filled($c['eyebrow']))<p class="eyebrow">{{ $c['eyebrow'] }}</p>@endif
                <h2 @class(['sec-heading text-display-md', 'mt-3' => filled($c['eyebrow'])])>{{ $c['title'] }}</h2>
                @if (filled($c['text']))<p class="sec-text mt-3">{{ $c['text'] }}</p>@endif
            </div>
            @include('sections.partials.cta-buttons', ['onDark' => $dark])
        </div>
    @else
        {{-- Banner card (navy), optionally with an image. --}}
        <div class="relative isolate overflow-hidden rounded-[2rem] bg-brand text-white shadow-[var(--shadow-lift)]" data-reveal>
            <div class="pattern-star absolute inset-0 -z-10 opacity-[0.06]" aria-hidden="true"></div>
            <div class="absolute -right-24 -bottom-32 -z-10 size-[420px] rounded-full bg-gold/15 blur-3xl" aria-hidden="true"></div>

            <div @class(['grid items-center', 'lg:grid-cols-[1.1fr_0.9fr]' => $variant === 'split' && $c['image']])>
                <div class="px-7 py-12 sm:px-12 sm:py-14 lg:px-16">
                    @if (filled($c['eyebrow']))<p class="eyebrow eyebrow-light">{{ $c['eyebrow'] }}</p>@endif
                    <h2 @class(['text-display-lg text-white', 'mt-4' => filled($c['eyebrow'])])>{{ $c['title'] }}</h2>
                    @if (filled($c['text']))<p class="mt-5 max-w-xl text-[1.0625rem] leading-relaxed text-white/75">{{ $c['text'] }}</p>@endif
                    <div class="mt-9">@include('sections.partials.cta-buttons', ['onDark' => true])</div>
                </div>
                @if ($variant === 'split' && $c['image'])
                    <div class="relative h-72 lg:h-full lg:min-h-[420px]">
                        <img src="{{ $c['image']->url('md') }}" srcset="{{ $c['image']->srcset() }}" sizes="(min-width: 1024px) 40vw, 100vw" alt="" loading="lazy" decoding="async" class="absolute inset-0 size-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-r from-brand via-brand/30 to-transparent max-lg:bg-gradient-to-t" aria-hidden="true"></div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</x-site.block>
