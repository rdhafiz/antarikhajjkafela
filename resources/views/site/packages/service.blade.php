@inject('site', App\Support\SiteContext::class)
@php
    use App\Support\Money;

    $locale = app()->getLocale();
    $name = $service->getTranslation('name', $locale);
    $summary = $service->getTranslation('summary', $locale);
    $image = $service->image ?? app(App\Support\Branding::class)->defaultBanner();
    $whatsapp = $site->whatsappUrl(__('Assalamu alaikum, I would like to know about your :name packages.', ['name' => $name]));
    [$minDays, $maxDays] = $stats['days'] ?? [null, null];
@endphp
<x-site.layout header="overlay">
    {{-- Hero: the service photo moves behind centered content, as on the home page. --}}
    <section class="hero-60 relative isolate flex flex-col overflow-hidden bg-brand-950 pt-20 text-white" data-hero>
        @if ($image)
            <div class="hero-media" data-parallax-bg aria-hidden="true">
                <img src="{{ $image->url('lg') }}" srcset="{{ $image->srcset() }}" sizes="100vw" alt="" fetchpriority="high">
            </div>
            <div class="absolute inset-0 -z-10 bg-gradient-to-b from-brand-950/80 via-brand-950/65 to-brand-950/90" aria-hidden="true"></div>
        @else
            <div class="pattern-star absolute inset-0 -z-10 opacity-[0.06]" aria-hidden="true"></div>
        @endif

        <div class="container-site flex flex-1 flex-col items-center justify-center py-10 text-center sm:py-12">
            <div class="hero-reveal flex max-w-3xl flex-col items-center" data-parallax-content>
                <x-site.breadcrumbs light class="mb-6 [&_ol]:justify-center" />
                <p class="eyebrow eyebrow-light justify-center">{{ __('Packages from Bangladesh') }}</p>
                <h1 class="mt-5 text-display-xl text-white">{{ $name }}</h1>
                @if (filled($summary))
                    <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-white/80">{{ $summary }}</p>
                @endif
                <div class="mt-9 flex w-full flex-col justify-center gap-3 sm:w-auto sm:flex-row">
                    @if ($packages->isNotEmpty())
                        <x-site.button href="#packages" variant="gold" size="lg" icon-right="chevron-down">{{ __('View packages') }}</x-site.button>
                    @endif
                    @if ($whatsapp)
                        <x-site.button :href="$whatsapp" external variant="outline-light" size="lg" icon="whatsapp">{{ __('Ask on WhatsApp') }}</x-site.button>
                    @else
                        <x-site.button :href="url('/contact')" variant="outline-light" size="lg">{{ __('Contact us') }}</x-site.button>
                    @endif
                </div>
            </div>
        </div>

        @if ($stats['count'])
            <div class="relative z-10 border-t border-white/10 bg-brand-950/85 backdrop-blur-md">
                <dl @class(['container-site grid gap-y-6 py-5 sm:py-6', 'grid-cols-3' => $stats['from'] && $minDays, 'grid-cols-2' => ! ($stats['from'] && $minDays)])>
                    <div class="flex flex-col-reverse items-center text-center">
                        <dt class="mt-1 text-[12px] tracking-wide text-white/60 sm:text-[13px]">{{ __('Packages') }}</dt>
                        <dd class="font-display text-xl font-semibold text-gold sm:text-3xl">{{ Money::digits($stats['count']) }}</dd>
                    </div>
                    @if ($stats['from'])
                        <div class="flex flex-col-reverse items-center border-l border-white/10 text-center">
                            <dt class="mt-1 text-[12px] tracking-wide text-white/60 sm:text-[13px]">{{ __('Starting from') }}</dt>
                            <dd class="font-display text-xl font-semibold text-gold sm:text-3xl">{{ Money::html($stats['from']) }}</dd>
                        </div>
                    @endif
                    @if ($minDays)
                        <div class="flex flex-col-reverse items-center border-l border-white/10 text-center">
                            <dt class="mt-1 text-[12px] tracking-wide text-white/60 sm:text-[13px]">{{ __('Duration') }}</dt>
                            <dd class="font-display text-xl font-semibold text-gold sm:text-3xl">
                                {{ trans_choice(':count day|:count days', $maxDays, ['count' => Money::digits($minDays === $maxDays ? $minDays : $minDays.'–'.$maxDays)]) }}
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>
        @endif
    </section>

    {{-- About: the description's plain paragraphs, centered and set large. --}}
    @if ($about['paragraphs'])
        <section class="relative isolate overflow-hidden bg-white py-20 sm:py-24 lg:py-28">
            <div class="pattern-star-ink pointer-events-none absolute -inset-y-[25%] inset-x-0 -z-10 opacity-[0.015]" data-parallax="0.2" aria-hidden="true"></div>
            <div class="container-site">
                <div class="mx-auto max-w-3xl text-center" data-reveal>
                    <h2 class="eyebrow justify-center">{{ __('About :name', ['name' => $name]) }}</h2>
                    @foreach ($about['paragraphs'] as $index => $paragraph)
                        <p @class([
                            'text-brand',
                            'mt-6 font-display text-[1.5rem] leading-[1.55] sm:text-[1.875rem]' => $index === 0,
                            'mt-5 text-[1.0625rem] leading-relaxed text-ink-2' => $index > 0,
                        ])>{{ $paragraph }}</p>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Quoted verses or hadith from the description, in the home page's verse style. --}}
    @foreach ($about['quotes'] as $quote)
        <section class="relative isolate overflow-hidden bg-brand py-20 text-white sm:py-24">
            <div class="pattern-star pointer-events-none absolute -inset-y-[30%] inset-x-0 -z-10 opacity-[0.05]" data-parallax="0.3" aria-hidden="true"></div>
            <div class="pointer-events-none absolute top-1/2 -left-24 -z-10 size-[420px] -translate-y-1/2 rounded-full border-[28px] border-gold/[0.07]" data-parallax="-0.25" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -right-16 -bottom-24 -z-10 size-72 rounded-full bg-gold/10 blur-3xl" data-parallax="0.2" aria-hidden="true"></div>
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

    {{-- Packages: a centered grid over a slowly moving pattern. --}}
    <section id="packages" class="relative isolate scroll-mt-20 overflow-hidden bg-sand-50 py-20 sm:py-24 lg:py-28">
        <div class="pattern-star-ink pointer-events-none absolute -inset-y-[20%] inset-x-0 -z-10 opacity-[0.025]" data-parallax="0.2" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -top-40 -right-40 -z-10 size-[520px] rounded-full bg-gold/10 blur-3xl" data-parallax="-0.15" aria-hidden="true"></div>
        <div class="container-site">
            <div class="mx-auto mb-14 max-w-2xl text-center" data-reveal>
                <p class="eyebrow justify-center">{{ __('Packages') }}</p>
                <h2 class="mt-4 text-display-lg text-brand">{{ __('Choose your :name package', ['name' => $name]) }}</h2>
                <p class="mt-5 text-[1.0625rem] leading-relaxed text-ink-2">{{ __('Hotels near the Haram, flights and scholars beside you. Compare the packages and book online.') }}</p>
            </div>

            @if ($packages->isEmpty())
                <div class="card-site mx-auto flex max-w-xl flex-col items-center px-6 py-16 text-center" data-reveal>
                    <x-site.icon name="calendar" class="size-10 text-gold-deep" />
                    <h3 class="mt-5 font-display text-display-sm text-brand">{{ __('New packages are being prepared') }}</h3>
                    <p class="mt-3 max-w-md text-ink-2">{{ __('Call or message us and we will share the latest packages and prices with you.') }}</p>
                </div>
            @else
                <div @class([
                    'grid gap-6',
                    'mx-auto max-w-md' => $packages->count() === 1,
                    'mx-auto max-w-4xl sm:grid-cols-2' => $packages->count() === 2,
                    'sm:grid-cols-2 lg:grid-cols-3' => $packages->count() >= 3,
                ])>
                    @foreach ($packages as $index => $package)
                        <x-site.package-card :package="$package" data-reveal :style="'--reveal-delay: '.(($index % 3) * 100).'ms'" />
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</x-site.layout>
