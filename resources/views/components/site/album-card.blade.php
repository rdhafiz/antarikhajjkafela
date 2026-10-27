@props(['album'])
@php
    use App\Support\Money;

    $locale = app()->getLocale();
    $images = $album->images_count ?? null;
    $videos = $album->videos_count ?? null;
@endphp
<div {{ $attributes }}>
<article class="group relative overflow-hidden rounded-[1.5rem] bg-brand-950 shadow-[var(--shadow-soft)] transition-[translate,box-shadow] duration-700 ease-[cubic-bezier(0.22,1,0.36,1)] hover:-translate-y-1.5 hover:shadow-[var(--shadow-lift)]">
    <div class="relative aspect-[4/3]">
        @if ($album->cover)
            <img
                src="{{ $album->cover->url('thumb') ?? $album->cover->url('sm') }}"
                srcset="{{ $album->cover->srcset() }}"
                sizes="(min-width: 1024px) 380px, (min-width: 640px) 50vw, 100vw"
                alt="{{ $album->cover->altText($album->getTranslation('title', $locale)) }}"
                loading="lazy"
                decoding="async"
                class="absolute inset-0 size-full object-cover transition-[scale] duration-[1200ms] ease-[cubic-bezier(0.22,1,0.36,1)] group-hover:scale-[1.05]"
            >
        @else
            <div class="pattern-star absolute inset-0 opacity-[0.08]" aria-hidden="true"></div>
        @endif
        <div class="absolute inset-0 bg-gradient-to-t from-brand-950/90 via-brand-950/25 to-transparent" aria-hidden="true"></div>

        <div class="absolute inset-x-0 bottom-0 p-6 text-center">
            @if ($album->event_date)
                <p class="text-[12px] font-semibold tracking-wider text-gold uppercase">{{ App\Support\Dates::format($album->event_date, 'F Y', $locale) }}</p>
            @endif
            <h3 class="mt-1.5 font-display text-display-sm text-white">
                <a href="{{ $album->url() }}" class="after:absolute after:inset-0 focus-visible:outline-none">{{ $album->getTranslation('title', $locale) }}</a>
            </h3>
            @if ($images !== null)
                <p class="mt-2.5 flex items-center justify-center gap-4 text-[13px] font-medium text-white/75">
                    @if ($images)
                        <span class="inline-flex items-center gap-1.5"><x-site.icon name="image" class="size-4" />{{ trans_choice(':count photo|:count photos', $images, ['count' => Money::digits($images)]) }}</span>
                    @endif
                    @if ($videos)
                        <span class="inline-flex items-center gap-1.5"><x-site.icon name="play" class="size-4" />{{ trans_choice(':count video|:count videos', $videos, ['count' => Money::digits($videos)]) }}</span>
                    @endif
                </p>
            @endif
        </div>
    </div>
</article>
</div>
