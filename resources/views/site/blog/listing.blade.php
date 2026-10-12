{{-- Blog page listing (page_listing section). Data from App\Domain\Blog\BlogListing. --}}
@php
    use App\Domain\Blog\PostPresenter;
    use App\Support\Money;

    $locale = app()->getLocale();
    $rest = $posts->getCollection()->when($lead, fn ($c) => $c->reject(fn ($p) => $p->is($lead)))->values();
    $title = filled($c['title'] ?? null) ? $c['title'] : ($first ? $page->getTranslation('title', $locale) : null);
    $headingTag = $first ? 'h1' : 'h2';
    $pill = 'shrink-0 rounded-full px-4.5 py-2 text-[14px] font-semibold transition-colors duration-300';
@endphp
<x-site.block :style="$style" :section="$section">
    <div class="pointer-events-none absolute -top-40 left-1/2 -z-10 size-[620px] -translate-x-1/2 rounded-full bg-gold/10 blur-3xl" data-parallax="-0.15" aria-hidden="true"></div>

    {{-- Centered heading (the page's main heading when the blog opens the page), then the filters. --}}
    @if ($title || filled($c['eyebrow'] ?? null) || filled($c['intro'] ?? null))
        <div class="mx-auto mb-9 max-w-2xl text-center" data-reveal>
            @if (filled($c['eyebrow'] ?? null))<p class="eyebrow justify-center">{{ $c['eyebrow'] }}</p>@endif
            @if ($title)
                <{{ $headingTag }} @class(['sec-heading text-display-lg', 'mt-4' => filled($c['eyebrow'] ?? null)])>{{ $title }}</{{ $headingTag }}>
            @endif
            @if (filled($c['intro'] ?? null))<p class="sec-text mt-5 text-[1.0625rem] leading-relaxed">{{ $c['intro'] }}</p>@endif
        </div>
    @endif

    @if ($categories->count() > 1 || $tag)
        <nav class="mx-auto mb-12 flex w-fit max-w-full gap-2 overflow-x-auto pb-1 sm:flex-wrap sm:justify-center" aria-label="{{ __('Categories') }}" data-reveal style="--reveal-delay: 80ms">
            <a href="{{ $blogUrl }}" @class([$pill, 'bg-brand text-white' => ! $category && ! $tag, 'bg-white text-ink-2 ring-1 ring-line hover:text-brand' => $category || $tag]) @if (! $category && ! $tag) aria-current="page" @endif>{{ __('All') }}</a>
            @foreach ($categories as $item)
                <a href="{{ $item->url() }}" @class([$pill, 'bg-brand text-white' => $category?->is($item), 'bg-white text-ink-2 ring-1 ring-line hover:text-brand' => ! $category?->is($item)]) @if ($category?->is($item)) aria-current="page" @endif>{{ $item->getTranslation('name', $locale) }}</a>
            @endforeach
            @if ($tag)
                <a href="{{ $blogUrl }}" class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-gold px-4 py-2 text-[14px] font-semibold text-brand-950" aria-label="{{ __('Remove filter') }}: #{{ $tag }}">
                    #{{ $tag }} <x-site.icon name="x" class="size-4" />
                </a>
            @endif
        </nav>
    @endif

    @if ($posts->isEmpty())
        <div class="mx-auto max-w-md py-10 text-center" data-reveal>
            <span class="sec-icon mx-auto size-14"><x-site.icon name="book" class="size-6" /></span>
            <p class="sec-heading mt-5 font-display text-display-sm">{{ __('No articles yet') }}</p>
            <p class="sec-text mt-2">{{ __('New guides and news will appear here soon.') }}</p>
            @if ($category || $tag)
                <a href="{{ $blogUrl }}" class="mt-5 inline-flex font-semibold text-brand hover:underline">{{ __('See all articles') }}</a>
            @endif
        </div>
    @else
        {{-- Newest article: its banner wide, with the details centered over it. --}}
        @if ($lead)
            @php
                $leadMinutes = app(PostPresenter::class)->minutes($lead);
                $excerpt = $lead->getTranslation('excerpt', $locale);
            @endphp
            <article class="group relative isolate mx-auto flex min-h-[24rem] max-w-6xl flex-col items-center justify-end overflow-hidden rounded-[2rem] bg-brand-950 px-6 pt-24 pb-10 text-center text-white shadow-[var(--shadow-lift)] sm:min-h-[30rem] sm:px-10 sm:pb-12">
                @if ($lead->banner)
                    <div class="absolute inset-0 -z-20 overflow-hidden">
                        <img src="{{ $lead->banner->url('lg') }}" srcset="{{ $lead->banner->srcset() }}" sizes="(min-width: 1200px) 1150px, 100vw" alt=""
                             class="size-full object-cover transition-[scale] duration-[1400ms] ease-[cubic-bezier(0.22,1,0.36,1)] group-hover:scale-[1.04]">
                    </div>
                @else
                    <div class="pattern-star absolute inset-0 -z-20 opacity-[0.08]" aria-hidden="true"></div>
                @endif
                <div class="absolute inset-0 -z-10 bg-gradient-to-t from-brand-950 via-brand-950/60 to-brand-950/10" aria-hidden="true"></div>

                <div class="flex max-w-2xl flex-col items-center" data-reveal>
                    <p class="flex flex-wrap items-center justify-center gap-x-3 gap-y-2 text-[13px] font-medium text-white/80">
                        @if ($lead->category)
                            <span class="rounded-full bg-gold px-3 py-1 text-[12px] font-bold text-brand-950">{{ $lead->category->getTranslation('name', $locale) }}</span>
                        @endif
                        <time datetime="{{ $lead->published_at?->toDateString() }}">{{ PostPresenter::date($lead->published_at) }}</time>
                        <span class="size-1 rounded-full bg-gold" aria-hidden="true"></span>
                        <span>{{ trans_choice(':count min read|:count min read', $leadMinutes, ['count' => Money::digits($leadMinutes)]) }}</span>
                    </p>
                    <{{ $first ? 'h2' : 'h3' }} class="mt-4 font-display text-display-lg text-white">
                        <a href="{{ $lead->url() }}" class="after:absolute after:inset-0 focus-visible:outline-none">{{ $lead->getTranslation('title', $locale) }}</a>
                    </{{ $first ? 'h2' : 'h3' }}>
                    @if (filled($excerpt))
                        <p class="mt-3 line-clamp-2 text-[1.0625rem] leading-relaxed text-white/80">{{ $excerpt }}</p>
                    @endif
                </div>
                <span class="mt-7 inline-flex items-center gap-2 rounded-full bg-gold px-6 py-3 text-[15px] font-semibold text-brand-950 shadow-[0_10px_30px_-12px_rgb(250_204_21/0.6)]" data-reveal style="--reveal-delay: 120ms" aria-hidden="true">
                    {{ __('Read article') }}
                    <x-site.icon name="arrow-right" class="size-4 transition-[translate] duration-500 group-hover:translate-x-1 rtl:rotate-180" />
                </span>
            </article>
        @endif

        {{-- The other articles: a centered grid (a short last row stays centered). --}}
        @if ($rest->isNotEmpty())
            <div @class(['mx-auto flex max-w-6xl flex-wrap justify-center gap-6', 'mt-8' => $lead])>
                @foreach ($rest as $index => $post)
                    <x-site.post-card :post="$post" center :heading-tag="$lead ? 'h3' : 'h2'" class="w-full sm:w-[calc(50%-0.75rem)] lg:w-[calc(33.333%-1rem)]" data-reveal :style="'--reveal-delay: '.(($index % 3) * 90).'ms'" />
                @endforeach
            </div>
        @endif

        <x-site.pagination :paginator="$posts" class="mt-14 flex justify-center" />
    @endif
</x-site.block>
