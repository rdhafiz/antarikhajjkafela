@props(['page', 'image' => null])
@php
    use App\Domain\Blog\PostPresenter;
    use App\Support\Seo\SeoText;

    $locale = app()->getLocale();
    $title = $page->getTranslation('title', $locale);
    $excerpt = SeoText::single($page->seo['description'] ?? null, $locale);
@endphp
<div {{ $attributes->class('h-full') }}>
<article class="group relative flex h-full flex-col overflow-hidden rounded-[1.5rem] border border-line bg-white shadow-[var(--shadow-soft)] transition-[translate,box-shadow] duration-700 ease-[cubic-bezier(0.22,1,0.36,1)] hover:-translate-y-1.5 hover:shadow-[var(--shadow-lift)]">
    <div class="relative aspect-[16/10] overflow-hidden bg-brand">
        @if ($image)
            <img
                src="{{ $image->url('sm') }}"
                srcset="{{ $image->srcset() }}"
                sizes="(min-width: 1024px) 380px, (min-width: 640px) 50vw, 100vw"
                alt="{{ $image->altText($title) }}"
                loading="lazy"
                decoding="async"
                class="absolute inset-0 size-full object-cover transition-[scale] duration-[1200ms] ease-[cubic-bezier(0.22,1,0.36,1)] group-hover:scale-[1.05]"
            >
        @else
            <div class="pattern-star absolute inset-0 opacity-[0.08]" aria-hidden="true"></div>
            <x-site.icon name="calendar" class="absolute top-1/2 left-1/2 size-14 -translate-x-1/2 -translate-y-1/2 text-gold/70" />
        @endif
    </div>

    <div class="flex flex-1 flex-col p-6">
        @if ($page->expires_at)
            <p class="text-[13px] font-medium text-ink-3">
                <time datetime="{{ $page->expires_at->toDateString() }}">{{ __('Ends :date', ['date' => PostPresenter::date($page->expires_at)]) }}</time>
            </p>
        @endif
        <h2 class="mt-3 font-display text-display-sm text-brand">
            <a href="{{ $page->url() }}" class="after:absolute after:inset-0 focus-visible:outline-none">{{ $title }}</a>
        </h2>
        @if ($excerpt)
            <p class="mt-3 line-clamp-3 text-[15px] leading-relaxed text-ink-2">{{ $excerpt }}</p>
        @endif
        <span class="mt-auto inline-flex items-center gap-1.5 pt-5 text-[14px] font-semibold text-brand">
            {{ __('View offer') }}
            <x-site.icon name="arrow-right" class="size-4 transition-transform group-hover:translate-x-1 rtl:rotate-180" />
        </span>
    </div>
</article>
</div>
