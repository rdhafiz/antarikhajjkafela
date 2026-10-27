@props(['post', 'lead' => false, 'headingTag' => 'h3', 'center' => false])
@php
    use App\Domain\Blog\PostPresenter;
    use App\Support\Money;

    $locale = app()->getLocale();
    $title = $post->getTranslation('title', $locale);
    $excerpt = $post->getTranslation('excerpt', $locale);
    $minutes = app(PostPresenter::class)->minutes($post);
@endphp
<div {{ $attributes->class('h-full') }}>
<article @class([
    'group relative flex h-full overflow-hidden rounded-[1.5rem] border border-line bg-white shadow-[var(--shadow-soft)] transition-[translate,box-shadow] duration-700 ease-[cubic-bezier(0.22,1,0.36,1)] hover:-translate-y-1.5 hover:shadow-[var(--shadow-lift)]',
    'flex-col lg:grid lg:grid-cols-[1.15fr_1fr]' => $lead,
    'flex-col' => ! $lead,
])>
    <div @class(['relative overflow-hidden bg-brand', 'aspect-[16/10] lg:aspect-auto lg:min-h-[420px]' => $lead, 'aspect-[16/10]' => ! $lead])>
        @if ($post->banner)
            <img
                src="{{ $post->banner->url($lead ? 'md' : 'sm') }}"
                srcset="{{ $post->banner->srcset() }}"
                sizes="{{ $lead ? '(min-width: 1024px) 640px, 100vw' : '(min-width: 1024px) 380px, (min-width: 640px) 50vw, 100vw' }}"
                alt="{{ $post->banner->altText($title) }}"
                @if (! $lead) loading="lazy" @endif
                decoding="async"
                class="absolute inset-0 size-full object-cover transition-[scale] duration-[1200ms] ease-[cubic-bezier(0.22,1,0.36,1)] group-hover:scale-[1.05]"
            >
        @else
            <div class="pattern-star absolute inset-0 opacity-[0.08]" aria-hidden="true"></div>
            <x-site.icon name="book" class="absolute top-1/2 left-1/2 size-14 -translate-x-1/2 -translate-y-1/2 text-gold/70" />
        @endif
        @if ($post->category)
            <span class="absolute top-4 left-4 rounded-full bg-white/95 px-3 py-1 text-[12px] font-semibold text-brand">{{ $post->category->getTranslation('name', $locale) }}</span>
        @endif
    </div>

    <div @class(['flex flex-1 flex-col', 'p-7 sm:p-9 lg:p-11 lg:justify-center' => $lead, 'p-6' => ! $lead, 'items-center text-center' => $center])>
        <p @class(['flex flex-wrap items-center gap-x-3 gap-y-1 text-[13px] font-medium text-ink-3', 'justify-center' => $center])>
            <time datetime="{{ $post->published_at?->toDateString() }}">{{ PostPresenter::date($post->published_at) }}</time>
            <span class="size-1 rounded-full bg-gold" aria-hidden="true"></span>
            <span>{{ trans_choice(':count min read|:count min read', $minutes, ['count' => Money::digits($minutes)]) }}</span>
        </p>
        <{{ $headingTag }} @class(['mt-3 font-display text-brand', 'text-display-md' => $lead, 'text-display-sm' => ! $lead])>
            <a href="{{ $post->url() }}" class="after:absolute after:inset-0 focus-visible:outline-none">{{ $title }}</a>
        </{{ $headingTag }}>
        @if ($excerpt)
            <p @class(['mt-3 leading-relaxed text-ink-2', 'text-[1.0625rem] line-clamp-4' => $lead, 'text-[15px] line-clamp-3' => ! $lead])>{{ $excerpt }}</p>
        @endif
        <span @class(['inline-flex items-center gap-1.5 text-[14px] font-semibold text-brand', 'mt-7' => $lead, 'mt-auto pt-5' => ! $lead])>
            {{ __('Read article') }}
            <x-site.icon name="arrow-right" class="size-4 transition-transform group-hover:translate-x-1 rtl:rotate-180" />
        </span>
    </div>
</article>
</div>
