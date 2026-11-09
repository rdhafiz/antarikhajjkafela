@props(['page', 'image' => null])
{{-- A campaign as a wide card, styled like the package cards on a service page (x-site.package-feature). --}}
@php
    use App\Domain\Blog\PostPresenter;
    use App\Support\Seo\SeoText;

    $locale = app()->getLocale();
    $title = $page->getTranslation('title', $locale);
    $excerpt = SeoText::single($page->seo['description'] ?? null, $locale);
@endphp
<article {{ $attributes->class(['pkg-feature relative overflow-hidden rounded-[2rem] border border-line bg-white shadow-[var(--shadow-soft)]']) }}>
    <div class="relative isolate flex aspect-[16/10] flex-col justify-between overflow-hidden bg-brand p-5 text-white sm:aspect-[16/6] sm:p-7 lg:aspect-[16/5]">
        @if ($image)
            <div class="absolute inset-0 -z-20 overflow-hidden">
                <img
                    src="{{ $image->url('lg') }}"
                    srcset="{{ $image->srcset() }}"
                    sizes="(min-width: 1200px) 1150px, 100vw"
                    alt="{{ $image->altText($title) }}" loading="lazy" decoding="async"
                    class="pkg-feature-img size-full object-cover"
                >
            </div>
        @else
            <div class="pattern-star absolute inset-0 -z-20 opacity-[0.08]" aria-hidden="true"></div>
        @endif
        <div class="absolute inset-0 -z-10 bg-gradient-to-t from-brand-950/90 via-brand-950/30 to-brand-950/10" aria-hidden="true"></div>

        <div class="flex flex-wrap items-start gap-2" data-reveal>
            <span class="rounded-full bg-gold px-3 py-1 text-[12px] font-bold text-brand-950">{{ __('Special offer') }}</span>
            @if ($page->expires_at)
                <span class="flex items-center gap-1.5 rounded-full bg-white/95 px-3 py-1 text-[12px] font-semibold text-brand">
                    <x-site.icon name="calendar" class="size-3.5 text-gold-deep" />
                    <time datetime="{{ $page->expires_at->toDateString() }}">{{ __('Ends :date', ['date' => PostPresenter::date($page->expires_at)]) }}</time>
                </span>
            @endif
        </div>

        <h2 class="max-w-xl font-display text-[1.6rem] leading-tight font-semibold text-white sm:text-[2rem]" data-reveal style="--reveal-delay: 100ms">
            <a href="{{ $page->url() }}" class="pkg-feature-title">{{ $title }}</a>
        </h2>
    </div>

    <div class="flex flex-col gap-6 p-5 sm:p-7 lg:flex-row lg:items-center lg:justify-between lg:gap-10">
        @if ($excerpt)
            <p class="max-w-2xl text-[15.5px] leading-relaxed text-ink-2" data-reveal>{{ $excerpt }}</p>
        @endif
        <div class="shrink-0" data-reveal style="--reveal-delay: 150ms">
            <x-site.button :href="$page->url()" variant="gold" icon-right="arrow-right">{{ __('View offer') }}</x-site.button>
        </div>
    </div>
</article>
