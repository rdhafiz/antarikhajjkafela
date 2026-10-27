@props(['package'])
@php
    $locale = app()->getLocale();
    $state = $package->bookingState();
    $name = $package->getTranslation('name', $locale);
@endphp
<div {{ $attributes->class('h-full') }}>
<article class="group relative flex h-full flex-col overflow-hidden rounded-[1.5rem] border border-line bg-white shadow-[var(--shadow-soft)] transition-[translate,box-shadow] duration-700 ease-[cubic-bezier(0.22,1,0.36,1)] hover:-translate-y-1.5 hover:shadow-[var(--shadow-lift)]">
    <div class="relative aspect-[16/10] overflow-hidden bg-brand">
        @if ($package->banner)
            <img
                src="{{ $package->banner->url('sm') }}"
                srcset="{{ $package->banner->srcset() }}"
                sizes="(min-width: 1024px) 380px, (min-width: 640px) 50vw, 100vw"
                alt="{{ $package->banner->altText($name) }}"
                loading="lazy"
                decoding="async"
                class="size-full object-cover transition-[scale] duration-[1200ms] ease-[cubic-bezier(0.22,1,0.36,1)] group-hover:scale-[1.05]"
            >
        @else
            <div class="pattern-star absolute inset-0 opacity-[0.08]" aria-hidden="true"></div>
            <x-site.icon name="kaaba" class="absolute top-1/2 left-1/2 size-16 -translate-x-1/2 -translate-y-1/2 text-gold/70" />
        @endif
        <div class="absolute inset-0 bg-gradient-to-t from-brand-950/60 via-transparent to-transparent" aria-hidden="true"></div>

        <div class="absolute top-4 left-4 flex flex-wrap gap-2">
            <span class="rounded-full bg-white/95 px-3 py-1 text-[12px] font-semibold text-brand">{{ $package->service?->getTranslation('name', $locale) }}</span>
            @if ($package->is_featured)
                <span class="rounded-full bg-gold px-3 py-1 text-[12px] font-bold text-brand-950">{{ __('Featured') }}</span>
            @endif
        </div>
        @if ($state !== 'open')
            <span class="absolute right-4 bottom-4 rounded-full bg-brand-950/85 px-3 py-1 text-[12px] font-semibold text-white">
                {{ $state === 'closed' ? __('Booking closed') : __('Opening soon') }}
            </span>
        @endif
    </div>

    <div class="flex flex-1 flex-col p-6">
        <h3 class="font-display text-display-sm text-brand">
            <a href="{{ $package->url() }}" class="after:absolute after:inset-0">{{ $name }}</a>
        </h3>
        @if ($summary = $package->getTranslation('summary', $locale))
            <p class="mt-2.5 line-clamp-2 text-[15px] leading-relaxed text-ink-2">{{ $summary }}</p>
        @endif

        <div class="mt-auto flex items-end justify-between gap-4 pt-6">
            <div>
                @if (! $package->show_price)
                    <p class="text-[12px] font-semibold tracking-wide text-ink-3 uppercase">{{ __('Price') }}</p>
                    <x-site.price-request class="block font-display text-[1.25rem] leading-tight font-semibold text-brand" />
                @elseif ($package->min_price)
                    <p class="text-[12px] font-semibold tracking-wide text-ink-3 uppercase">{{ __('Starting from') }}</p>
                    <p class="font-display text-[1.6rem] leading-tight font-semibold text-brand">{{ App\Support\Money::html($package->min_price) }}</p>
                @endif
            </div>
            @if ($package->duration_days)
                <p class="flex items-center gap-1.5 rounded-full bg-sand-100 px-3 py-1.5 text-[13px] font-semibold text-ink-2">
                    <x-site.icon name="calendar" class="size-4 text-gold-deep" />
                    {{ trans_choice(':count day|:count days', $package->duration_days, ['count' => App\Support\Money::digits($package->duration_days)]) }}
                </p>
            @endif
        </div>
    </div>
</article>
</div>
