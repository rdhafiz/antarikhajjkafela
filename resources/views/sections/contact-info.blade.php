@inject('site', App\Support\SiteContext::class)
@php
    $phones = $site->phones();
    $emails = $site->emails();
    $whatsapp = $site->whatsappUrl();
    $whatsappNumber = settings('contact.primary_whatsapp');
    $address = $site->address();
    $map = $c['show_map'] ? $site->mapEmbedUrl() : null;

    // One card per way to reach us; the row stays centered whatever is set.
    $cards = array_values(array_filter([
        $phones ? ['icon' => 'phone', 'label' => __('Phone'), 'links' => array_map(fn ($p) => ['href' => $p['href'], 'text' => $p['display'], 'ltr' => true], $phones), 'action' => ['href' => $phones[0]['href'], 'text' => __('Call us')]] : null,
        $whatsapp ? ['icon' => 'whatsapp', 'label' => 'WhatsApp', 'links' => $whatsappNumber ? [['href' => $whatsapp, 'text' => $whatsappNumber, 'ltr' => true, 'external' => true]] : [], 'action' => ['href' => $whatsapp, 'text' => __('Chat on WhatsApp'), 'external' => true]] : null,
        $emails ? ['icon' => 'mail', 'label' => __('Email'), 'links' => array_map(fn ($e) => ['href' => 'mailto:'.$e, 'text' => $e, 'ltr' => true], $emails), 'action' => null] : null,
        $address ? ['icon' => 'map-pin', 'label' => __('Office'), 'text' => $address, 'links' => [], 'action' => ['href' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($address), 'text' => __('Get directions'), 'external' => true]] : null,
    ]));
    $width = ['', 'sm:w-[calc(50%-0.75rem)]', 'sm:w-[calc(50%-0.75rem)]', 'sm:w-[calc(50%-0.75rem)] lg:w-[calc(33.333%-1rem)]', 'sm:w-[calc(50%-0.75rem)] lg:w-[calc(25%-1.125rem)]'][count($cards)] ?? '';
@endphp
<x-site.block :style="$style" :section="$section">
    <div class="pointer-events-none absolute -top-40 left-1/2 -z-10 size-[620px] -translate-x-1/2 rounded-full bg-gold/10 blur-3xl" data-parallax="-0.15" aria-hidden="true"></div>

    {{-- Centered heading; the page's main heading when it opens the page (no banner above). --}}
    <x-site.block-heading :c="$c" class="mb-12 sm:mb-14" :as="$first ? 'h1' : 'h2'" />

    <ul class="mx-auto flex max-w-6xl flex-wrap justify-center gap-6">
        @foreach ($cards as $index => $card)
            <li class="w-full {{ $width }}" data-reveal style="--reveal-delay: {{ $index * 90 }}ms">
                <div class="group flex h-full flex-col items-center rounded-[1.75rem] border border-line bg-white p-7 text-center shadow-[var(--shadow-soft)] transition-[translate,box-shadow,border-color] duration-700 ease-[cubic-bezier(0.22,1,0.36,1)] hover:-translate-y-1.5 hover:border-gold/60 hover:shadow-[var(--shadow-lift)]">
                    <span class="flex size-16 items-center justify-center rounded-full bg-brand text-gold transition-[background-color,color,rotate] duration-500 group-hover:-rotate-6 group-hover:bg-gold group-hover:text-brand-950">
                        <x-site.icon :name="$card['icon']" class="size-7" />
                    </span>
                    <p class="mt-5 text-[13px] font-semibold tracking-[0.14em] text-gold-deep uppercase">{{ $card['label'] }}</p>
                    <div class="mt-2 flex flex-col items-center gap-1">
                        @if (! empty($card['text']))
                            <address class="font-semibold text-brand not-italic">{{ $card['text'] }}</address>
                        @endif
                        @foreach ($card['links'] as $link)
                            <a href="{{ $link['href'] }}" @if (! empty($link['external'])) target="_blank" rel="noopener" @endif @if (! empty($link['ltr'])) dir="ltr" @endif
                               class="font-semibold break-all text-brand hover:underline">{{ $link['text'] }}</a>
                        @endforeach
                    </div>
                    @if ($card['action'])
                        <a href="{{ $card['action']['href'] }}" @if (! empty($card['action']['external'])) target="_blank" rel="noopener" @endif
                           class="mt-auto inline-flex items-center gap-1.5 pt-5 text-[14px] font-semibold text-gold-deep hover:text-brand">
                            {{ $card['action']['text'] }}
                            <x-site.icon name="arrow-right" class="size-4 transition-[translate] duration-500 group-hover:translate-x-1 rtl:rotate-180" />
                        </a>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>

    @if (filled($c['hours']))
        <p class="mx-auto mt-8 flex w-fit max-w-full items-start gap-2.5 rounded-full bg-sand-100 px-5 py-2.5 text-[14.5px] text-ink-2" data-reveal>
            <x-site.icon name="clock" class="mt-0.5 size-4.5 shrink-0 text-gold-deep" />
            <span><span class="font-semibold text-brand">{{ __('Office hours') }}:</span> <span class="whitespace-pre-line">{{ $c['hours'] }}</span></span>
        </p>
    @endif

    @if ($map)
        <div class="mx-auto mt-12 max-w-6xl overflow-hidden rounded-[1.75rem] shadow-[var(--shadow-lift)]" data-reveal>
            <iframe
                src="{{ $map }}"
                title="{{ __('Office location on Google Maps') }}"
                class="h-[380px] w-full"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                allowfullscreen
            ></iframe>
        </div>
    @endif
</x-site.block>
