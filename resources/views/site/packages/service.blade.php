@inject('site', App\Support\SiteContext::class)
@inject('guide', App\Domain\Travel\ServiceGuide::class)
@php
    use App\Support\Money;

    $locale = app()->getLocale();
    $name = $service->getTranslation('name', $locale);
    $summary = $service->getTranslation('summary', $locale);
    $headline = $service->getTranslation('headline', $locale) ?: $name;
    $highlight = $service->getTranslation('highlight', $locale);
    $whatsapp = $site->whatsappUrl(__('Assalamu alaikum, I would like to know about your :name packages.', ['name' => $name]));
    [$minDays, $maxDays] = $stats['days'] ?? [null, null];
    $pilgrimage = $service->isPilgrimage();
    $guided = $guide->sections($service);
@endphp
<x-site.layout header="overlay">
    <x-site.hero
        :eyebrow="$name.' · '.__('Packages from Bangladesh')"
        :title="$headline"
        :highlight="$highlight"
        :intro="$summary"
        :image="$service->image"
        :stats="[
            ['value' => $stats['count'] ? Money::digits($stats['count']) : null, 'label' => __('Packages')],
            ['value' => $stats['from'] ? Money::html($stats['from']) : null, 'label' => __('Starting from')],
            ['value' => $minDays ? trans_choice(':count day|:count days', $maxDays, ['count' => Money::digits($minDays === $maxDays ? $minDays : $minDays.'–'.$maxDays)]) : null, 'label' => __('Duration')],
        ]"
    >
        <div class="flex w-full flex-col justify-center gap-3 sm:w-auto sm:flex-row">
            @if ($packages->isNotEmpty())
                <x-site.button href="#packages" variant="gold" size="lg" icon-right="chevron-down">{{ __('View packages') }}</x-site.button>
            @endif
            @if ($whatsapp)
                <x-site.button :href="$whatsapp" external variant="whatsapp" size="lg" icon="whatsapp">{{ __('Ask on WhatsApp') }}</x-site.button>
            @else
                <x-site.button :href="url('/contact')" variant="outline-light" size="lg">{{ __('Contact us') }}</x-site.button>
            @endif
        </div>
    </x-site.hero>

    {{-- Packages: a soft sky band with shapes drifting behind the cards. --}}
    <section id="packages" class="section-light relative isolate scroll-mt-20 overflow-hidden bg-sky-soft py-20 sm:py-24 lg:py-28">
        <x-site.decor />
        <div class="container-site">
            <div class="mx-auto mb-14 max-w-2xl text-center" data-reveal>
                <p class="eyebrow justify-center">{{ __('Packages') }}</p>
                <h2 class="mt-4 text-display-lg text-brand">{{ $pilgrimage ? __('Choose your :name package', ['name' => $name]) : __('Choose your next trip') }}</h2>
                <p class="mt-5 text-[1.0625rem] leading-relaxed text-ink-2">{{ $pilgrimage ? __('Hotels near the Haram, flights and scholars beside you. Compare the packages and book online.') : __('Flights, hotels, transfers and sightseeing in one price. Compare the tours and book online.') }}</p>
            </div>

            @if ($packages->isEmpty())
                <div class="card-site mx-auto flex max-w-xl flex-col items-center px-6 py-16 text-center" data-reveal>
                    <x-site.icon name="calendar" class="size-10 text-gold-deep" />
                    <h3 class="mt-5 font-display text-display-sm text-brand">{{ __('New packages are being prepared') }}</h3>
                    <p class="mt-3 max-w-md text-ink-2">{{ __('Call or message us and we will share the latest packages and prices with you.') }}</p>
                </div>
            @else
                <div class="mx-auto grid max-w-6xl gap-8">
                    @foreach ($packages as $index => $package)
                        <x-site.package-feature :package="$package" :card="$cards[$package->id]" />
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    @if ($guided)
        {{-- What the packages include: colourful icon cards on white. --}}
        <section class="section-light relative isolate overflow-hidden bg-white py-20 sm:py-24 lg:py-28">
            <x-site.decor />
            <div class="container-site">
                <x-site.block-heading :c="[
                    'eyebrow' => __('Included'),
                    'title' => $guided['included']['title'],
                    'intro' => $guided['included']['intro'],
                ]" class="mb-14" />
                <ul class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($guided['included']['items'] as $index => $item)
                        <li class="sec-card group p-7" data-reveal style="--reveal-delay: {{ ($index % 3) * 90 }}ms">
                            <span class="sec-icon ic-tone ic-{{ $index % 6 + 1 }} size-14 transition-[rotate] duration-500 group-hover:-rotate-6">
                                <x-site.icon :name="$item['icon']" class="size-6" />
                            </span>
                            <h3 class="sec-heading mt-6 text-display-sm">{{ $item['title'] }}</h3>
                            <p class="sec-text mt-3 leading-relaxed">{{ $item['text'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        {{-- The journey: three photo cards (preparation, Makkah, Madinah; or plan, flights, destination). --}}
        <section class="section-light relative isolate overflow-hidden bg-peach-soft py-20 sm:py-24 lg:py-28">
            <x-site.decor />
            <div class="container-site">
                <x-site.block-heading :c="[
                    'eyebrow' => __('Your journey'),
                    'title' => $guided['journey']['title'],
                    'intro' => $guided['journey']['intro'],
                ]" class="mb-14" />
                <ul class="grid gap-5 md:grid-cols-3">
                    @foreach ($guided['journey']['cards'] as $index => $card)
                        <li class="photo-card group relative isolate flex min-h-[24rem] flex-col justify-end overflow-hidden rounded-[1.75rem] bg-brand-950 p-6 text-white shadow-[var(--shadow-lift)] sm:p-7 lg:min-h-[28rem]" data-reveal style="--reveal-delay: {{ $index * 90 }}ms">
                            <img src="{{ asset($card['image']) }}" alt="{{ $card['alt'] }}" width="800" height="1000" loading="lazy" decoding="async" class="photo-card-img absolute inset-0 -z-20 size-full object-cover">
                            <span class="photo-card-fade absolute inset-0 -z-10" aria-hidden="true"></span>
                            <span class="sec-icon ic-tone ic-{{ $index + 1 }} absolute top-6 start-6 size-12 rounded-2xl shadow-lg sm:top-7 sm:start-7">
                                <x-site.icon :name="$card['icon']" class="size-6" />
                            </span>
                            <span class="absolute top-7 end-6 font-display text-[1.5rem] leading-none font-semibold text-white/90 [text-shadow:0_1px_3px_rgb(0_0_0/0.55)] sm:end-7" aria-hidden="true">{{ App\Support\Money::digits(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) }}</span>
                            <h3 class="font-display text-[1.5rem] leading-tight font-semibold text-white">{{ $card['title'] }}</h3>
                            <p class="mt-2.5 text-[15px] leading-relaxed text-white/85">{{ $card['text'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- Quoted verses or hadith from the description, on navy with drifting shapes. --}}
    @foreach ($about['quotes'] as $quote)
        <section class="section-dark relative isolate overflow-hidden bg-brand py-20 text-white sm:py-24">
            <div class="pattern-star pointer-events-none absolute -inset-y-[30%] inset-x-0 -z-10 opacity-[0.05]" data-parallax="0.3" aria-hidden="true"></div>
            <x-site.decor />
            <div class="container-site">
                <figure class="mx-auto max-w-3xl text-center" data-reveal="wipe">
                    <x-site.icon name="moon" class="mx-auto size-8 text-gold" />
                    @if ($quote['lead'])
                        <p class="mt-6 text-[15px] text-white/70">{{ $quote['lead'] }}</p>
                    @endif
                    <blockquote @class(['font-display text-display-md text-white', 'italic' => $locale !== 'bn', 'mt-6' => ! $quote['lead'], 'mt-3' => $quote['lead']])>“{{ $quote['text'] }}”</blockquote>
                    @if ($quote['source'])
                        <figcaption class="mt-6 inline-flex items-center gap-3 text-[14px] font-semibold tracking-wide text-gold uppercase">
                            <span class="h-px w-8 bg-gold/60"></span>{{ $quote['source'] }}<span class="h-px w-8 bg-gold/60"></span>
                        </figcaption>
                    @endif
                </figure>
            </div>
        </section>
    @endforeach

    {{-- About: the description's plain paragraphs, centered and set large on a soft lilac band. --}}
    @if ($about['paragraphs'])
        <section class="section-light relative isolate overflow-hidden bg-lilac-soft py-20 sm:py-24 lg:py-28">
            <x-site.decor />
            <div class="container-site">
                <div class="mx-auto max-w-3xl text-center">
                    <h2 class="eyebrow justify-center" data-reveal>{{ __('About :name', ['name' => $name]) }}</h2>
                    @foreach ($about['paragraphs'] as $index => $paragraph)
                        <p data-reveal style="--reveal-delay: 80ms" @class([
                            'text-brand',
                            'mt-6 font-display text-[1.5rem] leading-[1.55] sm:text-[1.875rem]' => $index === 0,
                            'mt-5 text-[1.0625rem] leading-relaxed text-ink-2' => $index > 0,
                        ])>{{ $paragraph }}</p>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

</x-site.layout>
