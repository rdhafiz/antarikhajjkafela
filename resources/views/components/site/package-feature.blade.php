@props(['package', 'card', 'showService' => false])
{{--
    A package as a wide card on its service page: the banner (at its own 16:6 shape) with the
    name and starting price, then the summary and flight beside every package option (tier)
    with its hotels and price, each option linking to booking.
    The frame stays in place while its parts reveal in turn as they scroll into view:
    the banner wipes open, then the summary, the flight, each option and the buttons.
--}}
@php
    use App\Support\Money;

    $locale = app()->getLocale();
    $name = $package->getTranslation('name', $locale);
    $summary = $package->getTranslation('summary', $locale);
    $open = $card['state'] === 'open';
@endphp
<article {{ $attributes->class(['pkg-feature relative overflow-hidden rounded-[2rem] border border-line bg-white shadow-[var(--shadow-soft)]']) }}>
    <div class="relative isolate flex aspect-[4/3] flex-col justify-between overflow-hidden bg-brand p-6 text-white sm:aspect-[16/7] sm:p-8 lg:aspect-[16/6] lg:p-10" data-reveal="wipe">
        @if ($package->banner)
            {{-- Own layer, so the wipe clips this wrapper and the photo keeps its slow hover zoom. --}}
            <div class="absolute inset-0 -z-20 overflow-hidden">
                <img
                    src="{{ $package->banner->url('lg') }}"
                    srcset="{{ $package->banner->srcset() }}"
                    sizes="(min-width: 1200px) 1150px, 100vw"
                    alt="" loading="lazy" decoding="async"
                    class="pkg-feature-img size-full object-cover"
                >
            </div>
        @else
            <div class="pattern-star absolute inset-0 -z-20 opacity-[0.08]" aria-hidden="true"></div>
        @endif
        <div class="absolute inset-0 -z-10 bg-gradient-to-t from-brand-950/90 via-brand-950/30 to-brand-950/10" aria-hidden="true"></div>

        <div class="flex flex-wrap items-start gap-2">
            @if ($showService && $package->service)
                <span class="rounded-full bg-white/95 px-3 py-1 text-[12px] font-semibold text-brand">{{ $package->service->getTranslation('name', $locale) }}</span>
            @endif
            @if ($package->is_featured)
                <span class="rounded-full bg-gold px-3 py-1 text-[12px] font-bold text-brand-950">{{ __('Featured') }}</span>
            @endif
            @if ($package->duration_days)
                <span class="flex items-center gap-1.5 rounded-full bg-white/95 px-3 py-1 text-[12px] font-semibold text-brand">
                    <x-site.icon name="calendar" class="size-3.5 text-gold-deep" />
                    {{ trans_choice(':count day|:count days', $package->duration_days, ['count' => Money::digits($package->duration_days)]) }}
                </span>
            @endif
            @unless ($open)
                <span class="rounded-full bg-brand-950/85 px-3 py-1 text-[12px] font-semibold text-white">{{ $card['state'] === 'closed' ? __('Booking closed') : __('Opening soon') }}</span>
            @endunless
        </div>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <h3 class="max-w-xl font-display text-display-md text-white">
                <a href="{{ $package->url() }}" class="pkg-feature-title">{{ $name }}</a>
            </h3>
            @if ($package->min_price)
                <div class="shrink-0 sm:text-end">
                    <p class="text-[12px] font-semibold tracking-[0.14em] text-white/70 uppercase">{{ __('Starting from') }}</p>
                    <p class="mt-1 font-display text-[2rem] leading-none font-semibold text-gold sm:text-[2.5rem]">{{ Money::html($package->min_price) }}</p>
                </div>
            @endif
        </div>
    </div>

    <div class="grid gap-8 p-6 sm:p-8 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)] lg:grid-rows-[auto_1fr] lg:gap-x-12 lg:p-10">
        <div class="lg:col-start-1 lg:row-start-1">
            @if (filled($summary))
                <p class="text-[1.0625rem] leading-relaxed text-ink-2" data-reveal>{{ $summary }}</p>
            @endif

            @if ($card['flight'])
                <p class="mt-5 flex items-start gap-2.5 text-[14.5px] leading-snug text-ink-2" data-reveal style="--reveal-delay: 100ms">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-sand-100"><x-site.icon name="plane" class="size-4 text-gold-deep" /></span>
                    <span class="pt-1.5">
                        <span class="font-semibold text-brand">{{ $card['flight']['airline'] }}</span>
                        @if (filled($card['flight']['schedule'] ?? null)) · {{ $card['flight']['schedule'] }}@endif
                    </span>
                </p>
            @endif
        </div>

        @if ($card['tiers'])
            <div class="lg:col-start-2 lg:row-span-2 lg:row-start-1">
                <p class="text-[12px] font-semibold tracking-[0.14em] text-ink-3 uppercase" data-reveal>{{ __('Package options') }}</p>
                <ul class="mt-3 divide-y divide-line overflow-hidden rounded-2xl border border-line">
                    @foreach ($card['tiers'] as $index => $tier)
                        <li data-reveal="left" style="--reveal-delay: {{ 80 + $index * 110 }}ms">
                            <a
                                href="{{ $open ? route('bookings.create', ['slug' => $package->slug, 'option' => $tier['option']]) : $package->url().'#prices' }}"
                                class="pkg-tier block px-4 py-3.5 sm:px-5"
                            >
                                <span class="flex items-start justify-between gap-4">
                                    <span class="min-w-0 pt-0.5 font-semibold text-brand">{{ $tier['name'] }}</span>
                                    <span class="flex shrink-0 items-center gap-2.5 text-end">
                                        <span>
                                            <span class="block font-display text-[1.25rem] leading-tight font-semibold text-brand">{{ Money::html($tier['price']) }}</span>
                                            <span class="block text-[12px] text-ink-3">{{ $tier['unit'] }}</span>
                                        </span>
                                        <x-site.icon name="arrow-right" class="pkg-tier-arrow size-4 shrink-0 text-gold-deep rtl:rotate-180" />
                                    </span>
                                </span>
                                @foreach ($tier['hotels'] as $hotel)
                                    <span class="mt-1 flex gap-1.5 text-[13.5px] leading-snug text-ink-3">
                                        <x-site.icon name="hotel" class="mt-0.5 size-3.5 shrink-0 text-gold-deep" />
                                        <span class="min-w-0">
                                            @if (filled($hotel['heading'] ?? null))<span class="font-semibold text-ink-2">{{ $hotel['heading'] }}:</span>@endif
                                            {{ collect([$hotel['subheading'] ?? null, $hotel['description'] ?? null])->filter()->implode(' · ') }}
                                        </span>
                                    </span>
                                @endforeach
                                <span class="sr-only">{{ $open ? __('Book now') : __('View details') }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex flex-col gap-3 sm:flex-row lg:col-start-1 lg:row-start-2 lg:flex-col lg:self-end xl:flex-row" data-reveal style="--reveal-delay: 150ms">
            @if ($open)
                <x-site.button :href="route('bookings.create', $package->slug)" variant="gold" icon-right="arrow-right">{{ __('Book now') }}</x-site.button>
            @endif
            <x-site.button :href="$package->url()" variant="outline">{{ __('View full details') }}</x-site.button>
        </div>
    </div>
</article>
