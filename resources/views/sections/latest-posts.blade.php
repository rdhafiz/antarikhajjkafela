@php
    $magazine = ($c['layout'] ?? 'grid') === 'magazine' && $posts->count() >= 2;
@endphp
@if ($posts->isNotEmpty())
    <x-site.block :style="$style" :section="$section">
        @if ($magazine)
            @php
                $lead = $posts->first();
                $rest = $posts->skip(1)->values();
                $dark = $style['tone'] === 'dark';
                $locale = app()->getLocale();
                $leadMinutes = app(App\Domain\Blog\PostPresenter::class)->minutes($lead);
            @endphp
            {{-- Editorial layout: heading row, the lead story over its photo, the rest as a compact list. --}}
            <div class="mb-10 flex flex-col gap-6 border-b border-[var(--sec-border)] pb-8 lg:flex-row lg:items-end lg:justify-between" data-reveal>
                <x-site.block-heading :c="$c" align="left" />
                @if ($c['cta'])
                    <x-site.link-button :link="$c['cta']" :variant="$dark ? 'gold' : 'outline'" icon-right="arrow-right" class="hidden shrink-0 lg:inline-flex" />
                @endif
            </div>

            <div class="grid gap-8 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] lg:gap-12">
                <article class="news-lead group relative isolate flex min-h-[26rem] flex-col justify-end overflow-hidden rounded-[1.75rem] bg-brand-950 text-white sm:min-h-[30rem]" data-reveal="scale">
                    @if ($lead->banner)
                        <img
                            src="{{ $lead->banner->url('md') }}"
                            srcset="{{ $lead->banner->srcset() }}"
                            sizes="(min-width: 1024px) 680px, 100vw"
                            alt="" loading="lazy" decoding="async"
                            class="news-lead-img absolute inset-0 -z-20 size-full object-cover"
                        >
                    @else
                        <div class="pattern-star absolute inset-0 -z-20 opacity-[0.08]" aria-hidden="true"></div>
                    @endif
                    <div class="absolute inset-0 -z-10 bg-gradient-to-t from-brand-950 via-brand-950/70 to-brand-950/5" aria-hidden="true"></div>

                    <div class="p-7 sm:p-10">
                        <p class="flex flex-wrap items-center gap-x-3 gap-y-2 text-[13px] font-medium text-white/80">
                            @if ($lead->category)
                                <span class="rounded-full bg-gold px-3 py-1 text-[12px] font-semibold text-brand-950">{{ $lead->category->getTranslation('name', $locale) }}</span>
                            @endif
                            <time datetime="{{ $lead->published_at?->toDateString() }}">{{ App\Domain\Blog\PostPresenter::date($lead->published_at) }}</time>
                            <span class="size-1 rounded-full bg-gold" aria-hidden="true"></span>
                            <span>{{ trans_choice(':count min read|:count min read', $leadMinutes, ['count' => App\Support\Money::digits($leadMinutes)]) }}</span>
                        </p>
                        <h3 class="mt-4 max-w-xl font-display text-display-md text-white">
                            <a href="{{ $lead->url() }}" class="after:absolute after:inset-0 focus-visible:outline-none">{{ $lead->getTranslation('title', $locale) }}</a>
                        </h3>
                        @if (filled($excerpt = $lead->getTranslation('excerpt', $locale)))
                            <p class="mt-3 line-clamp-2 max-w-lg text-[1rem] leading-relaxed text-white/75">{{ $excerpt }}</p>
                        @endif
                        <span class="mt-6 inline-flex items-center gap-2 text-[14px] font-semibold text-gold">
                            {{ __('Read article') }}
                            <x-site.icon name="arrow-right" class="size-4 transition-[translate] duration-500 group-hover:translate-x-1 rtl:rotate-180" />
                        </span>
                    </div>
                </article>

                <ol class="flex flex-col">
                    @foreach ($rest as $index => $post)
                        <li data-reveal style="--reveal-delay: {{ $index * 90 }}ms" class="news-row group relative grid grid-cols-[6.5rem_minmax(0,1fr)] items-center gap-5 border-b border-[var(--sec-border)] py-5 first:pt-0 last:border-b-0 sm:grid-cols-[8.5rem_minmax(0,1fr)] lg:flex-1">
                            <div class="relative aspect-square overflow-hidden rounded-2xl bg-brand sm:aspect-[4/3]">
                                @if ($post->banner)
                                    <img src="{{ $post->banner->url('sm') }}" alt="" loading="lazy" decoding="async" class="news-row-img size-full object-cover">
                                @else
                                    <x-site.icon name="book" class="absolute top-1/2 left-1/2 size-8 -translate-x-1/2 -translate-y-1/2 text-gold/70" />
                                @endif
                            </div>
                            <div class="min-w-0">
                                <p class="sec-muted flex flex-wrap items-center gap-x-2.5 text-[12.5px] font-medium">
                                    @if ($post->category)
                                        <span class="font-semibold text-[var(--sec-eyebrow,var(--color-gold-deep))]">{{ $post->category->getTranslation('name', $locale) }}</span>
                                        <span class="size-1 rounded-full bg-gold" aria-hidden="true"></span>
                                    @endif
                                    <time datetime="{{ $post->published_at?->toDateString() }}">{{ App\Domain\Blog\PostPresenter::date($post->published_at) }}</time>
                                </p>
                                <h3 class="sec-heading mt-1.5 line-clamp-2 font-display text-[1.125rem] leading-[1.4] font-semibold sm:text-[1.25rem]">
                                    <a href="{{ $post->url() }}" class="news-row-link after:absolute after:inset-0 focus-visible:outline-none">{{ $post->getTranslation('title', $locale) }}</a>
                                </h3>
                                @if (filled($excerpt = $post->getTranslation('excerpt', $locale)))
                                    <p class="sec-text mt-1.5 line-clamp-1 hidden text-[14.5px] sm:block">{{ $excerpt }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        @else
            <x-site.block-heading :c="$c" class="mb-14" />
            <div @class([
                'grid gap-6',
                'sm:grid-cols-2 lg:grid-cols-3' => $posts->count() >= 3,
                'mx-auto max-w-4xl sm:grid-cols-2' => $posts->count() === 2,
                'mx-auto max-w-md' => $posts->count() === 1,
            ])>
                @foreach ($posts as $index => $post)
                    <x-site.post-card :post="$post" data-reveal :style="'--reveal-delay: '.(($index % 3) * 80).'ms'" />
                @endforeach
            </div>
        @endif
        @if ($c['cta'])
            <div @class(['mt-12 text-center', 'lg:hidden' => $magazine])>
                <x-site.link-button :link="$c['cta']" :variant="$style['tone'] === 'dark' ? 'gold' : 'primary'" icon-right="arrow-right" />
            </div>
        @endif
    </x-site.block>
@endif
