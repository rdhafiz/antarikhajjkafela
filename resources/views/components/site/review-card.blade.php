@props(['review', 'withVideo' => true, 'tone' => null])
@php
    $locale = app()->getLocale();
    $name = $review->getTranslation('name', $locale);
    $intro = $review->getTranslation('intro', $locale);
    $text = $review->getTranslation('text', $locale);
    $videoId = $withVideo ? $review->youtubeId() : null;
    $long = mb_strlen((string) $text) > 320;
    $initials = collect(preg_split('/\s+/u', preg_replace('/[^\p{L}\s]/u', '', $name)))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('');
@endphp
<figure {{ $attributes->class('sec-card relative flex flex-col rounded-[1.5rem] p-6 sm:p-7') }}>
    @if ($videoId)
        <x-site.youtube :id="$videoId" :title="$name" class="-mx-1 -mt-1 mb-6" rounded="rounded-xl" />
    @endif

    <div class="flex items-center justify-between gap-4">
        @if ($review->rating)
            <x-site.stars :rating="$review->rating" />
        @endif
        @if ($tone)
            {{-- Colourful quote tile (.ic-1 … .ic-6), as on the other card grids. --}}
            <span class="sec-icon ic-tone ic-{{ $tone }} ml-auto size-11 shrink-0 rounded-xl" aria-hidden="true"><x-site.icon name="quote" class="size-5" /></span>
        @else
            <x-site.icon name="quote" class="ml-auto size-8 text-gold/60" />
        @endif
    </div>

    @if (filled($text))
        <blockquote class="mt-4 flex-1" @if ($long) x-data="{ open: false }" @endif>
            <p class="sec-text text-[15.5px] leading-relaxed whitespace-pre-line" @if ($long) :class="open || 'line-clamp-6'" @endif>{{ $text }}</p>
            @if ($long)
                <button type="button" class="mt-2 text-[14px] font-semibold text-[var(--sec-eyebrow)] hover:underline" @click="open = !open" x-text="open ? @js(__('Show less')) : @js(__('Read more'))" :aria-expanded="open">{{ __('Read more') }}</button>
            @endif
        </blockquote>
    @endif

    <figcaption class="mt-6 flex items-center gap-3.5 border-t border-[var(--sec-border)] pt-5">
        @if ($review->photo)
            <img src="{{ $review->photo->url('sm') }}" alt="" width="48" height="48" loading="lazy" decoding="async" class="size-12 shrink-0 rounded-full object-cover ring-2 ring-gold/50">
        @else
            <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-brand font-display text-[17px] font-semibold text-gold ring-2 ring-gold/40" aria-hidden="true">{{ $initials }}</span>
        @endif
        <span class="min-w-0">
            <span class="sec-heading block font-semibold">{{ $name }}</span>
            @if ($intro)
                <span class="sec-muted block text-[13px]">{{ $intro }}</span>
            @endif
        </span>
    </figcaption>
</figure>
