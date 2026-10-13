{{-- Gallery page listing (page_listing section). Data from App\Domain\Gallery\GalleryListing. --}}
@php
    use App\Support\Money;

    $locale = app()->getLocale();
    $title = filled($c['title'] ?? null) ? $c['title'] : ($first ? $page->getTranslation('title', $locale) : null);
    $rest = $featured ? $albums->getCollection()->slice(1)->values() : $albums->getCollection();
    $headingTag = $first ? 'h1' : 'h2';
@endphp
<x-site.block :style="$style" :section="$section">
    <div class="pointer-events-none absolute -top-40 left-1/2 -z-10 size-[620px] -translate-x-1/2 rounded-full bg-gold/10 blur-3xl" data-parallax="-0.15" aria-hidden="true"></div>

    {{-- Centered heading; the page's main heading when the gallery opens the page. --}}
    @if ($title || filled($c['eyebrow'] ?? null) || filled($c['intro'] ?? null))
        <div class="mx-auto mb-12 max-w-2xl text-center sm:mb-14" data-reveal>
            @if (filled($c['eyebrow'] ?? null))<p class="eyebrow justify-center">{{ $c['eyebrow'] }}</p>@endif
            @if ($title)
                <{{ $headingTag }} @class(['sec-heading text-display-lg', 'mt-4' => filled($c['eyebrow'] ?? null)])>{{ $title }}</{{ $headingTag }}>
            @endif
            @if (filled($c['intro'] ?? null))<p class="sec-text mt-5 text-[1.0625rem] leading-relaxed">{{ $c['intro'] }}</p>@endif
        </div>
    @endif

    @if ($albums->isEmpty())
        <div class="mx-auto max-w-md py-10 text-center" data-reveal>
            <span class="sec-icon mx-auto size-14"><x-site.icon name="images" class="size-6" /></span>
            <p class="sec-heading mt-5 font-display text-display-sm">{{ __('Photos coming soon') }}</p>
        </div>
    @else
        <h2 class="sr-only">{{ __('Albums') }}</h2>

        {{-- Newest album: a wide cover with its details over it. --}}
        @if ($featured)
            <article class="mx-auto max-w-6xl">
                <div class="relative isolate flex min-h-[24rem] flex-col items-start justify-end overflow-hidden rounded-[2rem] bg-brand-950 px-6 pt-24 pb-9 text-start text-white shadow-[var(--shadow-lift)] sm:min-h-[30rem] sm:px-12 sm:pb-12">
                    @if ($featured->cover)
                        <img src="{{ $featured->cover->url('lg') ?? $featured->cover->url('md') }}" srcset="{{ $featured->cover->srcset() }}" sizes="(min-width: 1200px) 1150px, 100vw" alt=""
                             class="absolute inset-0 -z-20 size-full object-cover">
                    @else
                        <div class="pattern-star absolute inset-0 -z-20 opacity-[0.08]" aria-hidden="true"></div>
                    @endif
                    <div class="absolute inset-0 -z-10 bg-gradient-to-r from-brand-950/90 via-brand-950/45 to-transparent rtl:bg-gradient-to-l" aria-hidden="true"></div>
                    <div class="absolute inset-x-0 bottom-0 -z-10 h-2/3 bg-gradient-to-t from-brand-950/85 to-transparent" aria-hidden="true"></div>

                    <div class="flex max-w-xl flex-col items-start" data-reveal>
                        <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[13px] font-semibold text-gold">
                            <span class="rounded-full bg-gold px-3 py-1 text-[12px] font-bold text-brand-950">{{ __('Latest album') }}</span>
                            @if ($featured->event_date)<span>{{ App\Support\Dates::format($featured->event_date, 'j F Y', $locale) }}</span>@endif
                        </p>
                        <h3 class="mt-4 font-display text-display-lg text-white">{{ $featured->getTranslation('title', $locale) }}</h3>
                        @if (filled($description = $featured->getTranslation('description', $locale)))
                            <p class="mt-3 line-clamp-2 text-[1.0625rem] leading-relaxed text-white/80">{{ $description }}</p>
                        @endif
                    </div>
                    <div class="mt-7 flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:gap-6" data-reveal style="--reveal-delay: 120ms">
                        <p class="flex items-center gap-4 text-[14px] font-medium text-white/80">
                            @if ($featured->images_count)
                                <span class="inline-flex items-center gap-1.5"><x-site.icon name="image" class="size-4 text-gold" />{{ trans_choice(':count photo|:count photos', $featured->images_count, ['count' => Money::digits($featured->images_count)]) }}</span>
                            @endif
                            @if ($featured->videos_count)
                                <span class="inline-flex items-center gap-1.5"><x-site.icon name="play" class="size-4 text-gold" />{{ trans_choice(':count video|:count videos', $featured->videos_count, ['count' => Money::digits($featured->videos_count)]) }}</span>
                            @endif
                        </p>
                        <x-site.button :href="$featured->url()" variant="gold" icon-right="arrow-right">{{ __('View album') }}</x-site.button>
                    </div>
                </div>
            </article>
        @endif

        {{-- Other albums: a centered grid (a short last row stays centered). --}}
        @if ($rest->isNotEmpty())
            @if ($featured)
                <div class="mx-auto mt-16 mb-8 max-w-xl text-center" data-reveal>
                    <p class="eyebrow justify-center">{{ __('More albums') }}</p>
                </div>
            @endif
            <div class="mx-auto flex max-w-6xl flex-wrap justify-center gap-6">
                @foreach ($rest as $index => $album)
                    <x-site.album-card :album="$album" class="w-full sm:w-[calc(50%-0.75rem)] lg:w-[calc(33.333%-1rem)]" data-reveal :style="'--reveal-delay: '.(($index % 3) * 90).'ms'" />
                @endforeach
            </div>
        @endif

        <x-site.pagination :paginator="$albums" class="mt-14 flex justify-center" />
    @endif
</x-site.block>
