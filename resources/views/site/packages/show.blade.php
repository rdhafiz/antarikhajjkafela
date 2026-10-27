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
            $p['showPrice']
                ? ['value' => $p['from'] ? Money::html($p['from']) : null, 'label' => __('Starting from')]
                : ['value' => __('Price on request'), 'label' => __('Price'), 'small' => true],
            ['value' => $p['duration'] ? trans_choice(':count day|:count days', $p['duration'], ['count' => Money::digits($p['duration'])]) : null, 'label' => __('Duration')],
            ['value' => new Illuminate\Support\HtmlString('<span class=\'size-2.5 shrink-0 rounded-full '.($bookable ? 'bg-emerald' : ($booking['state'] === 'upcoming' ? 'bg-gold' : 'bg-red-400')).'\'></span>'.e($booking['label'])), 'label' => __('Booking'), 'small' => true],
            ['value' => $airlines ?: null, 'label' => __('Airline'), 'small' => true],
        ]"
    >
        <div class="flex w-full flex-col justify-center gap-3 sm:w-auto sm:flex-row">
            <x-site.button href="#prices" variant="gold" size="lg" icon-right="arrow-right">{{ $p['showPrice'] ? __('See prices & hotels') : __('See options & hotels') }}</x-site.button>
            @if ($whatsapp = $enquire())
                <x-site.button :href="$whatsapp" variant="whatsapp" size="lg" icon="whatsapp" external>{{ __('Ask on WhatsApp') }}</x-site.button>
            @endif
        </div>
    </x-site.hero>

    @php
        // In-page sections for the sticky bar: options, flights, then each custom section that has a heading.
        $nav = collect([
            $p['types'] ? ['id' => 'prices', 'label' => __('Options & hotels')] : null,
            $p['flights'] ? ['id' => 'flights', 'label' => __('Flights')] : null,
        ])->filter()->merge(
            collect($p['sections'])->map(fn ($s, $i) => filled($s['heading']) ? ['id' => 'details-'.($i + 1), 'label' => $s['badge'] ?: $s['heading']] : null)->filter()
        )->values();
    @endphp

    {{-- Sticky section bar: where you are on the page, with booking always in reach. --}}
    @if ($nav->count() > 1)
        <div x-data="sectionNav" class="sticky top-20 z-20 border-b border-line bg-white/95 backdrop-blur-md">
            <div class="container-site flex items-center gap-4">
                <nav class="no-scrollbar -mx-1 flex min-w-0 flex-1 gap-1 overflow-x-auto py-2.5" aria-label="{{ __('On this page') }}" x-ref="links">
                    @foreach ($nav as $item)
                        <a href="#{{ $item['id'] }}" data-target="{{ $item['id'] }}"
                           class="section-nav-link shrink-0 rounded-full px-4 py-2 text-[14px] font-semibold whitespace-nowrap"
                           :class="active === '{{ $item['id'] }}' && 'is-active'"
                           :aria-current="active === '{{ $item['id'] }}' ? 'true' : null">{{ $item['label'] }}</a>
                    @endforeach
                </nav>
                <div class="hidden shrink-0 items-center gap-4 sm:flex">
                    @if (! $p['showPrice'])
                        <x-site.price-request link class="text-[14px] font-semibold text-brand" />
                    @elseif ($p['from'])
                        <p class="text-end leading-tight">
                            <span class="block text-[11px] font-semibold tracking-[0.12em] text-ink-3 uppercase">{{ __('Starting from') }}</span>
                            <span class="font-display text-[1.25rem] font-semibold text-brand">{{ Money::html($p['from']) }}</span>
                        </p>
                    @endif
                    @if ($bookable)
                        <x-site.button :href="route('bookings.create', $package->slug)" variant="gold" size="sm" icon-right="arrow-right">{{ __('Free Booking Now') }}</x-site.button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Options & hotels on a soft sky band: package types on one side, the chosen type's options and hotels on the other. --}}
    @if ($p['types'])
        <section id="prices" class="section-light relative isolate scroll-mt-36 overflow-clip bg-sky-soft py-16 sm:py-20 lg:py-24" x-data="{ tab: 0 }">
            <x-site.decor />
            <div class="container-site">
                <div class="mx-auto mb-12 max-w-2xl text-center" data-reveal>
                    <p class="eyebrow justify-center">{{ $p['showPrice'] ? __('Prices & hotels') : __('Options & hotels') }}</p>
                    <h2 class="mt-4 text-display-lg text-brand">{{ __('Choose the option that suits you') }}</h2>
                </div>

                <div @class(['grid gap-6 lg:gap-8', 'lg:grid-cols-[minmax(0,0.72fr)_minmax(0,1.28fr)]' => count($p['types']) > 1])>
                    @if (count($p['types']) > 1)
                        <div role="tablist" aria-label="{{ __('Package types') }}" aria-orientation="vertical" class="flex flex-col gap-3 lg:sticky lg:top-40 lg:self-start"
                             @keydown.down.prevent="tab = (tab + 1) % {{ count($p['types']) }}; $nextTick(() => $el.querySelectorAll('[role=tab]')[tab].focus())"
                             @keydown.up.prevent="tab = (tab + {{ count($p['types']) - 1 }}) % {{ count($p['types']) }}; $nextTick(() => $el.querySelectorAll('[role=tab]')[tab].focus())">
                            @foreach ($p['types'] as $i => $type)
                                @php
                                    $typeFrom = collect($type['prices'])->min('price');
                                @endphp
                                <div class="flex flex-col" data-reveal style="--reveal-delay: {{ $i * 100 }}ms">
                                    <button type="button" role="tab" id="type-tab-{{ $i }}" aria-controls="type-panel-{{ $i }}"
                                            @if ($i === 0) aria-selected="true" @else aria-selected="false" tabindex="-1" @endif
                                            :aria-selected="(tab === {{ $i }}).toString()" :tabindex="tab === {{ $i }} ? 0 : -1"
                                            @click="tab = {{ $i }}; if (window.innerWidth < 1024) window.scrollTo({ top: $el.closest('[role=tablist]').getBoundingClientRect().bottom + window.scrollY + 24 - 156, behavior: 'smooth' })"
                                            @class(['type-tab group/tab relative flex items-center gap-4 rounded-2xl border p-4 text-start sm:p-5', 'is-active' => $i === 0])
                                            :class="{ 'is-active': tab === {{ $i }} }">
                                        <span class="type-tab-num flex size-10 shrink-0 items-center justify-center rounded-full font-display text-[15px] font-semibold tabular-nums">{{ Money::digits(str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)) }}</span>
                                        <span class="min-w-0 flex-1">
                                            @if ($type['code'])<span class="type-tab-code block text-[11px] font-bold tracking-[0.14em] uppercase">{{ $type['code'] }}</span>@endif
                                            <span class="type-tab-name block font-display text-[1.125rem] leading-snug font-semibold">{{ $type['name'] }}</span>
                                        </span>
                                        @if ($typeFrom)
                                            <span class="shrink-0 text-end">
                                                <span class="type-tab-from block text-[11px] font-semibold uppercase">{{ __('From') }}</span>
                                                <span class="type-tab-price block font-display text-[1.125rem] leading-tight font-semibold">{{ Money::html($typeFrom) }}</span>
                                            </span>
                                        @endif
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="min-w-0">
                        @foreach ($p['types'] as $i => $type)
                            <div role="tabpanel" id="type-panel-{{ $i }}" aria-labelledby="type-tab-{{ $i }}" x-show="tab === {{ $i }}" @if ($i > 0) x-cloak @endif
                                 x-transition:enter="transition duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]" x-transition:enter-start="opacity-0 translate-y-3"
                                 class="space-y-6">
                                @if (count($p['types']) === 1)
                                    <h3 class="font-display text-display-sm text-brand" data-reveal>{{ $type['name'] }}</h3>
                                @endif

                                @foreach ($type['prices'] as $price)
                                    <article class="option-card overflow-hidden rounded-[1.75rem] border border-line bg-white shadow-[var(--shadow-soft)]">
                                        <header class="flex flex-wrap items-end justify-between gap-x-6 gap-y-3 border-b border-line bg-gradient-to-br from-white to-sand-50 p-6 sm:p-8" data-reveal>
                                            <div class="min-w-0">
                                                {{-- The type's name and its short description (e.g. "7 days"), inside the card. --}}
                                                <p class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                                                    <span class="text-[12px] font-semibold tracking-[0.14em] text-gold-deep uppercase">{{ $type['name'] }}</span>
                                                    @if ($type['description'])
                                                        <span class="rounded-full bg-sand-100 px-3 py-1 text-[13px] font-medium text-ink-2">{{ $type['description'] }}</span>
                                                    @endif
                                                </p>
                                                <h3 class="mt-1.5 font-display text-display-sm text-brand">{{ $price['title'] }}</h3>
                                                @if ($price['description'])<p class="mt-2 text-[15px] text-ink-2">{{ $price['description'] }}</p>@endif
                                            </div>
                                            @if ($price['price'] !== null)
                                                <p class="text-end">
                                                    <span class="block font-display text-[2.25rem] leading-none font-semibold text-brand">{{ Money::html($price['price']) }}</span>
                                                    <span class="mt-1 block text-[13px] text-ink-3">{{ PackagePresenter::unitLabel($price['unit']) }}</span>
                                                </p>
                                            @else
                                                <x-site.price-request link class="font-display text-[1.375rem] leading-tight font-semibold text-brand" />
                                            @endif
                                        </header>

                                        @if ($price['hotels'])
                                            <div class="grid divide-y divide-line sm:grid-cols-2 sm:divide-x sm:divide-y-0 rtl:sm:divide-x-reverse">
                                                @foreach ($price['hotels'] as $h => $hotel)
                                                    <div class="flex gap-4 bg-white p-6 sm:p-7" data-reveal style="--reveal-delay: {{ 80 + $h * 120 }}ms">
                                                        <span class="sec-icon ic-tone ic-{{ $h % 2 ? 3 : 1 }} size-12 shrink-0 rounded-2xl"><x-site.icon name="hotel" class="size-6" /></span>
                                                        <div class="min-w-0">
                                                            <p class="text-[12px] font-semibold tracking-[0.14em] text-ink-3 uppercase">{{ $hotel['heading'] }}</p>
                                                            @if ($hotel['subheading'])<p class="mt-1 font-display text-[1.1875rem] leading-snug font-semibold text-brand">{{ $hotel['subheading'] }}</p>@endif
                                                            @if ($hotel['description'])
                                                                <p class="mt-2 flex gap-1.5 text-[14px] leading-relaxed text-ink-2">
                                                                    <x-site.icon name="map-pin" class="mt-1 size-3.5 shrink-0 text-gold-deep" />
                                                                    <span>{{ $hotel['description'] }}</span>
                                                                </p>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        <footer class="flex flex-col gap-3 border-t border-line p-6 sm:flex-row sm:items-center sm:p-7" data-reveal style="--reveal-delay: 150ms">
                                            @if ($bookable)
                                                <x-site.button :href="route('bookings.create', ['slug' => $package->slug, 'option' => $price['id']])" variant="primary" icon-right="arrow-right">{{ __('Book this option') }}</x-site.button>
                                            @else
                                                <span class="rounded-full bg-sand-100 px-5 py-3 text-center text-[14px] font-semibold text-ink-3">{{ $booking['label'] }}</span>
                                            @endif
                                            @if ($phones)
                                                <x-site.button :href="$phones[0]['href']" variant="outline" icon="phone">{{ __('Call') }}</x-site.button>
                                            @endif
                                            @if ($whatsapp = $enquire($type['name'].' · '.$price['title']))
                                                <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-[14px] font-semibold text-[#15803d] hover:underline sm:ms-auto">
                                                    <x-site.icon name="whatsapp" class="size-4" />{{ __('Ask about this option') }}
                                                </a>
                                            @endif
                                        </footer>
                                    </article>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>

                <p class="mt-10 text-center text-[15px]" data-reveal>
                    <a href="{{ route('bookings.status') }}" class="inline-flex items-center gap-2 font-semibold text-brand hover:underline">
                        <x-site.icon name="ticket" class="size-4 text-gold-deep" />{{ __('Already booked? Find your booking') }}
                    </a>
                </p>
                <p class="mx-auto mt-4 max-w-3xl text-center text-[13px] leading-relaxed text-ink-2" data-reveal>{{ ($package->service?->isPilgrimage() !== false ? __('Prices may change if the Saudi government, airlines or exchange rates change their fees. Please pay only through our official channels.') : __('Prices may change with airline fares, hotel rates or exchange rates. Please pay only through our official channels.')) }}</p>
            </div>
        </section>
    @endif

    {{-- Flights: a navy travel band. --}}
    @if ($p['flights'])
        <section id="flights" class="section-dark relative isolate scroll-mt-36 overflow-hidden bg-brand-950 py-16 text-white sm:py-20 lg:py-24">
            <div class="pattern-star pointer-events-none absolute -inset-y-[25%] inset-x-0 -z-10 opacity-[0.05]" data-parallax="0.25" aria-hidden="true"></div>
            <x-site.decor />
            <div class="container-site grid items-center gap-10 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] lg:gap-16">
                <div>
                    <div data-reveal>
                        <p class="eyebrow eyebrow-light">{{ __('Travel') }}</p>
                        <h2 class="mt-4 text-display-lg text-white">{{ __('Flights') }}</h2>
                    </div>
                    <dl class="mt-8 grid grid-cols-2 gap-4">
                        @if ($p['duration'])
                            <div class="rounded-2xl border border-white/10 bg-white/[0.05] p-5" data-reveal style="--reveal-delay: 80ms">
                                <dt class="text-[12px] font-semibold tracking-[0.14em] text-white/55 uppercase">{{ __('Duration') }}</dt>
                                <dd class="mt-1 font-display text-2xl font-semibold text-gold" data-count>{{ trans_choice(':count day|:count days', $p['duration'], ['count' => Money::digits($p['duration'])]) }}</dd>
                            </div>
                        @endif
                        <div class="rounded-2xl border border-white/10 bg-white/[0.05] p-5" data-reveal style="--reveal-delay: 180ms">
                            <dt class="text-[12px] font-semibold tracking-[0.14em] text-white/55 uppercase">{{ __('Booking') }}</dt>
                            <dd class="mt-2 flex items-center gap-2 text-[15px] font-semibold text-white">
                                <span @class(['size-2.5 shrink-0 rounded-full', 'bg-emerald' => $bookable, 'bg-gold' => $booking['state'] === 'upcoming', 'bg-red-400' => $booking['state'] === 'closed'])></span>
                                {{ $booking['label'] }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="grid gap-5">
                    @foreach ($p['flights'] as $flight)
                        <article class="flight-card relative overflow-hidden rounded-[1.75rem] border border-white/10 bg-white/[0.06] p-7 sm:p-9">
                            <div class="flex items-start gap-5" data-reveal>
                                <span class="sec-icon ic-tone ic-2 size-14 shrink-0 rounded-full"><x-site.icon name="plane" class="size-7" /></span>
                                <div class="min-w-0">
                                    <h3 class="font-display text-display-sm text-white">{{ $flight['airline'] }}</h3>
                                    @if ($flight['schedule'])<p class="mt-1.5 text-[15px] font-semibold text-gold">{{ $flight['schedule'] }}</p>@endif
                                </div>
                            </div>
                            {{-- A route line the plane travels along when the card comes into view. --}}
                            <div class="flight-route relative mt-7 h-px bg-white/15" aria-hidden="true" data-reveal style="--reveal-delay: 120ms">
                                <span class="absolute -top-1 left-0 size-2 rounded-full bg-gold"></span>
                                <span class="absolute -top-1 right-0 size-2 rounded-full bg-gold"></span>
                                <x-site.icon name="plane" class="flight-plane absolute -top-2.5 size-5 text-gold rtl:-scale-x-100" />
                            </div>
                            @if ($flight['description'])<p class="mt-6 text-[15px] leading-relaxed whitespace-pre-line text-white/75" data-reveal style="--reveal-delay: 200ms">{{ $flight['description'] }}</p>@endif
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Custom sections (included, notes, gifts…), each on the next soft shade. --}}
    @foreach ($p['sections'] as $index => $s)
        @include('site.packages.section', ['s' => $s, 'index' => $index])
    @endforeach

    {{-- Related: the same wide cards as the package lists. --}}
    @if ($related->isNotEmpty())
        <section class="section-light relative isolate overflow-hidden bg-white py-16 sm:py-20 lg:py-24">
            <x-site.decor />
            <div class="container-site">
                <div class="mx-auto mb-12 max-w-2xl text-center" data-reveal>
                    <p class="eyebrow justify-center">{{ $p['service']->getTranslation('name', $locale) }}</p>
                    <h2 class="mt-4 text-display-lg text-brand">{{ __('You may also like') }}</h2>
                </div>
                <div class="mx-auto grid max-w-6xl gap-8">
                    @foreach ($related as $item)
                        <x-site.package-feature :package="$item" :card="$relatedCards[$item->id]" />
                    @endforeach
                </div>
                <div class="mt-10 text-center" data-reveal>
                    <x-site.button :href="$p['service']->url()" variant="outline" icon-right="arrow-right">{{ __('All :service packages', ['service' => $p['service']->getTranslation('name', $locale)]) }}</x-site.button>
                </div>
            </div>
        </section>
    @endif
</x-site.layout>
