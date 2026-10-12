@props([
    'title',
    'eyebrow' => null,
    'highlight' => null,    // gold italic line under the title
    'intro' => null,
    'image' => null,        // App\Models\Media background; falls back to the default banner
    'stats' => [],          // list of ['value' => string|Htmlable, 'label' => string, 'small' => bool] for the strip at the bottom
    'top' => true,          // at the top of the page: 75vh, parallax, overlay header
    'tag' => 'section',
])
{{--
    The inner-page hero, shared by every page except home: centered text over a photo that moves
    behind it, at three quarters of the screen, with an optional strip of key figures.
    Buttons or extra details go in the slot, under the introduction.
--}}
@php
    $image ??= app(App\Support\Branding::class)->defaultBanner();
    $stats = array_values(array_filter($stats, fn ($stat) => filled((string) ($stat['value'] ?? ''))));
@endphp
<{{ $tag }} {{ $attributes->class(['relative isolate flex flex-col overflow-hidden bg-brand-950 text-white', 'hero-75 pt-20' => $top]) }} @if ($top) data-hero @endif>
    @if ($image)
        <div class="hero-media" @if ($top) data-parallax-bg @endif aria-hidden="true">
            <img src="{{ $image->url('lg') }}" srcset="{{ $image->srcset() }}" sizes="100vw" alt="" @if ($top) fetchpriority="high" @else loading="lazy" @endif>
        </div>
        <div class="absolute inset-0 -z-10 bg-gradient-to-b from-brand-950/80 via-brand-950/65 to-brand-950/90" aria-hidden="true"></div>
    @else
        <div class="absolute inset-0 -z-20 bg-gradient-to-br from-brand via-brand-900 to-brand-950" aria-hidden="true"></div>
        <div class="pattern-star absolute inset-0 -z-10 opacity-[0.06]" aria-hidden="true"></div>
    @endif

    <div class="container-site flex flex-1 flex-col items-center justify-center py-12 text-center sm:py-14">
        <div class="hero-reveal flex max-w-4xl flex-col items-center" @if ($top) data-parallax-content @endif>
            @if (filled($eyebrow))
                <p class="eyebrow eyebrow-light justify-center">{{ $eyebrow }}</p>
            @endif
            <h1 @class(['text-display-xl text-white', 'mt-5' => filled($eyebrow)])>
                {{ $title }}
                @if (filled($highlight))<span class="hero-line text-gold italic">{{ $highlight }}</span>@endif
            </h1>
            @if (filled($intro))
                <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-white/80">{{ $intro }}</p>
            @endif
            @if ($slot->isNotEmpty())
                <div class="mt-9 flex w-full flex-col items-center">{{ $slot }}</div>
            @endif
        </div>
    </div>

    @if ($stats)
        <div class="relative z-10 border-t border-white/10 bg-brand-950/85 backdrop-blur-md">
            <dl @class(['container-site grid gap-y-6 py-5 sm:py-6', ['grid-cols-1', 'grid-cols-2', 'grid-cols-3', 'grid-cols-2 sm:grid-cols-4'][min(4, count($stats)) - 1]])>
                @foreach ($stats as $index => $stat)
                    <div @class(['flex flex-col-reverse items-center px-2 text-center', 'border-l border-white/10' => $index > 0 && count($stats) < 4, 'sm:border-l sm:border-white/10 sm:first:border-l-0' => count($stats) >= 4])>
                        <dt class="mt-1 text-[12px] tracking-wide text-white/60 sm:text-[13px]">{{ $stat['label'] }}</dt>
                        <dd @class([
                            'font-display text-xl font-semibold text-gold sm:text-3xl' => ! ($stat['small'] ?? false),
                            'flex min-h-8 items-center gap-2 text-[15px] font-semibold text-white sm:min-h-9 sm:text-lg' => $stat['small'] ?? false,
                        ]) @unless ($stat['small'] ?? false) data-count @endunless>{{ $stat['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endif
</{{ $tag }}>
