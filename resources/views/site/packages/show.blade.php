@inject('site', App\Support\SiteContext::class)
@php
    use App\Domain\Travel\PackagePresenter;
    use App\Support\Money;

    $locale = app()->getLocale();
    $booking = $p['booking'];
    $bookable = $booking['state'] === 'open';
    $phones = $site->phones();
    $airlines = collect($p['flights'])->pluck('airline')->filter()->unique()->implode(', ');
    $enquire = fn (?string $option = null) => $site->whatsappUrl(trim(__('Assalamu alaikum, I am interested in the ":package" package', ['package' => $p['name']]).($option ? ' – '.$option : '').'.'));
@endphp
<x-site.layout header="overlay">
    {{-- Hero, with the key facts in the strip at the bottom --}}
    <x-site.hero
        :eyebrow="$p['service']->getTranslation('name', $locale)"
        :title="$p['name']"
        :intro="$p['summary']"
        :image="$p['banner']"
        :stats="[
            ['value' => $p['from'] ? Money::html($p['from']) : null, 'label' => __('Starting from')],
            ['value' => $p['duration'] ? trans_choice(':count day|:count days', $p['duration'], ['count' => Money::digits($p['duration'])]) : null, 'label' => __('Duration')],
            ['value' => new Illuminate\Support\HtmlString('<span class=\'size-2.5 shrink-0 rounded-full '.($bookable ? 'bg-emerald' : ($booking['state'] === 'upcoming' ? 'bg-gold' : 'bg-red-400')).'\'></span>'.e($booking['label'])), 'label' => __('Booking'), 'small' => true],
            ['value' => $airlines ?: null, 'label' => __('Airline'), 'small' => true],
        ]"
    >
        <div class="flex w-full flex-col justify-center gap-3 sm:w-auto sm:flex-row">
            <x-site.button href="#prices" variant="gold" size="lg" icon-right="arrow-right">{{ __('See prices & hotels') }}</x-site.button>
            @if ($whatsapp = $enquire())
                <x-site.button :href="$whatsapp" variant="whatsapp" size="lg" icon="whatsapp" external>{{ __('Ask on WhatsApp') }}</x-site.button>
            @endif
        </div>
    </x-site.hero>

    {{-- Prices: package types → pricing options → hotels --}}
    @if ($p['types'])
        <section id="prices" class="section-light scroll-mt-24 bg-sand-50 py-16 sm:py-20" x-data="{ tab: 0 }">
            <div class="container-site">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="eyebrow">{{ __('Prices & hotels') }}</p>
                        <h2 class="sec-heading mt-4 text-display-lg">{{ __('Choose the option that suits you') }}</h2>
                    </div>
                    @if (count($p['types']) > 1)
                        <div class="flex flex-wrap gap-2" role="tablist" aria-label="{{ __('Package types') }}">
                            @foreach ($p['types'] as $i => $type)
                                <button type="button" role="tab" :aria-selected="(tab === {{ $i }}).toString()" @click="tab = {{ $i }}"
                                        class="rounded-full px-5 py-2.5 text-[15px] font-semibold transition-colors"
                                        :class="tab === {{ $i }} ? 'bg-brand text-white shadow-[var(--shadow-soft)]' : 'bg-white text-ink-2 ring-1 ring-line hover:text-brand'">
                                    {{ $type['name'] }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                @foreach ($p['types'] as $i => $type)
                    <div role="tabpanel" x-show="tab === {{ $i }}" @if ($i > 0) x-cloak @endif class="mt-10">
                        @if ($type['description'])
                            <p class="mb-8 max-w-3xl text-[1.0625rem] leading-relaxed text-ink-2">{{ $type['description'] }}</p>
                        @endif
                        <div @class(['grid gap-6', 'lg:grid-cols-2' => count($type['prices']) > 1, 'max-w-3xl' => count($type['prices']) === 1, 'xl:grid-cols-3' => count($type['prices']) >= 3])>
                            @foreach ($type['prices'] as $price)
                                <article class="flex flex-col overflow-hidden rounded-[1.5rem] border border-line bg-white shadow-[var(--shadow-soft)]">
                                    <header class="border-b border-line bg-gradient-to-br from-white to-sand-100 p-6">
                                        <div class="flex flex-wrap items-start justify-between gap-4">
                                            <div>
                                                @if ($type['code'])<p class="text-[11px] font-bold tracking-[0.14em] text-ink-3 uppercase">{{ $type['code'] }}</p>@endif
                                                <h3 class="mt-1 font-display text-display-sm text-brand">{{ $price['title'] }}</h3>
                                            </div>
                                            <p class="text-right">
                                                <span class="block font-display text-[1.75rem] leading-tight font-semibold text-brand">{{ Money::html($price['price']) }}</span>
                                                <span class="text-[13px] text-ink-3">{{ PackagePresenter::unitLabel($price['unit']) }}</span>
                                            </p>
                                        </div>
                                        @if ($price['description'])<p class="mt-3 text-[15px] text-ink-2">{{ $price['description'] }}</p>@endif
                                    </header>

                                    @if ($price['hotels'])
                                        <ul class="flex-1 divide-y divide-line px-6">
                                            @foreach ($price['hotels'] as $hotel)
                                                <li class="flex gap-4 py-4">
                                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand"><x-site.icon name="hotel" class="size-5" /></span>
                                                    <div>
                                                        <p class="font-semibold text-ink">{{ $hotel['heading'] }}</p>
                                                        @if ($hotel['subheading'])<p class="text-[14px] font-medium text-gold-deep">{{ $hotel['subheading'] }}</p>@endif
                                                        @if ($hotel['description'])<p class="mt-1 text-[14px] leading-relaxed text-ink-2">{{ $hotel['description'] }}</p>@endif
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif

                                    <footer class="flex flex-col gap-2.5 p-6 pt-4 sm:flex-row">
                                        @if ($bookable)
                                            <x-site.button :href="route('bookings.create', ['slug' => $package->slug, 'option' => $price['id']])" variant="primary" icon-right="arrow-right" class="flex-1">{{ __('Book this option') }}</x-site.button>
                                        @else
                                            <span class="flex-1 rounded-full bg-sand-100 px-5 py-3 text-center text-[14px] font-semibold text-ink-3">{{ $booking['label'] }}</span>
                                        @endif
                                        @if ($phones)
                                            <x-site.button :href="$phones[0]['href']" variant="outline" icon="phone">{{ __('Call') }}</x-site.button>
                                        @endif
                                    </footer>
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                <p class="mt-8 text-[13px] text-ink-3">{{ __('Prices may change if the Saudi government, airlines or exchange rates change their fees. Please pay only through our official channels.') }}</p>
            </div>
        </section>
    @endif

    {{-- Flights --}}
    @if ($p['flights'])
        <section id="flights" class="section-light scroll-mt-24 bg-white py-16 sm:py-20">
            <div class="container-site">
                <p class="eyebrow">{{ __('Travel') }}</p>
                <h2 class="sec-heading mt-4 text-display-lg">{{ __('Flights') }}</h2>
                <div class="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($p['flights'] as $flight)
                        <div class="sec-card flex gap-4 p-6">
                            <span class="sec-icon size-12 shrink-0"><x-site.icon name="plane" class="size-6" /></span>
                            <div>
                                <p class="sec-heading font-display text-[1.2rem] font-semibold">{{ $flight['airline'] }}</p>
                                @if ($flight['schedule'])<p class="mt-1 text-[14px] font-semibold text-gold-deep">{{ $flight['schedule'] }}</p>@endif
                                @if ($flight['description'])<p class="sec-text mt-2 text-[15px] leading-relaxed whitespace-pre-line">{{ $flight['description'] }}</p>@endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Custom sections --}}
    @foreach ($p['sections'] as $index => $s)
        @include('site.packages.section', ['s' => $s, 'index' => $index])
    @endforeach

    {{-- Related --}}
    @if ($related->isNotEmpty())
        <section class="bg-sand-100 py-16 sm:py-20">
            <div class="container-site">
                <div class="mb-10 flex flex-wrap items-end justify-between gap-4">
                    <h2 class="text-display-md text-brand">{{ __('You may also like') }}</h2>
                    <a href="{{ $p['service']->url() }}" class="link-underline font-semibold text-brand">{{ __('All :service packages', ['service' => $p['service']->getTranslation('name', $locale)]) }}</a>
                </div>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($related as $item)
                        <x-site.package-card :package="$item" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-site.layout>
