@props(['cta' => true])
@inject('site', App\Support\SiteContext::class)
@inject('branding', App\Support\Branding::class)
@php
    $phones = $site->phones();
    $emails = $site->emails();
    $whatsapp = $site->whatsappUrl();
    $footerMenu = $site->menu('footer');
    $mainMenu = collect($site->menu('primary'))->filter(fn ($item) => $item['url'])->take(6);
@endphp

<footer class="relative overflow-hidden bg-gradient-to-b from-brand-900 to-brand-950 text-white/70">
    <div class="pattern-star pointer-events-none absolute inset-0 opacity-[0.045]" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -top-40 right-[-10%] size-[520px] rounded-full bg-gold/10 blur-3xl" aria-hidden="true"></div>

    {{-- Call to action --}}
    @if ($cta)
    <div class="container-site relative pt-16 sm:pt-20">
        <div class="flex flex-col gap-8 rounded-[1.75rem] border border-white/10 bg-white/[0.04] p-7 sm:p-10 lg:flex-row lg:items-center lg:justify-between" data-reveal>
            <div class="max-w-xl">
                <p class="eyebrow eyebrow-light">{{ __('Begin your journey') }}</p>
                <h2 class="mt-4 text-display-md text-white">{{ __('Planning your next trip? Talk to our team.') }}</h2>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row">
                @if ($phones)
                    <a href="{{ $phones[0]['href'] }}" class="btn btn-gold btn-lg"><x-site.icon name="phone" /> {{ $phones[0]['display'] }}</a>
                @endif
                @if ($whatsapp)
                    <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="btn btn-whatsapp btn-lg"><x-site.icon name="whatsapp" /> WhatsApp</a>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- Columns --}}
    <div class="container-site relative grid grid-cols-2 gap-x-6 gap-y-12 py-16 lg:grid-cols-[1.4fr_1fr_1fr_1.3fr] lg:gap-10">
        {{-- Each column reveals a beat after the one before. --}}
        <div class="col-span-2 max-w-sm lg:col-span-1" data-reveal>
            <a href="{{ url('/') }}" class="inline-flex items-center gap-3">
                <span class="flex size-14 items-center justify-center rounded-2xl bg-white p-1.5">
                    <img src="{{ $branding->iconUrl(96) }}" alt="" aria-hidden="true" width="48" height="48" class="size-full object-contain" loading="lazy" decoding="async">
                </span>
                <span class="font-display text-[1.35rem] leading-tight font-semibold text-white">{{ $site->name() }}</span>
            </a>
            <p class="mt-5 text-[15px] leading-relaxed">{{ setting_t('branding.short_description') }}</p>
            @if ($socials = $site->socials())
                <div class="mt-6 flex flex-wrap gap-2">
                    @foreach ($socials as $social)
                        <a href="{{ $social['url'] }}" target="_blank" rel="noopener" aria-label="{{ $social['label'] }}"
                           class="flex size-10 items-center justify-center rounded-full border border-white/12 text-white/80 transition-colors hover:border-gold hover:bg-gold hover:text-brand-950">
                            <x-site.icon :name="$social['key']" class="size-[18px]" />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($mainMenu->isNotEmpty())
            <nav aria-labelledby="footer-explore" data-reveal style="--reveal-delay: 90ms">
                <h2 id="footer-explore" class="font-sans text-[13px] font-bold tracking-[0.14em] text-gold uppercase">{{ __('Explore') }}</h2>
                <ul class="mt-5 space-y-3 text-[15px]">
                    @foreach ($mainMenu as $item)
                        <li><a href="{{ $item['url'] }}" class="link-underline transition-colors hover:text-white">{{ $item['label'] }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif

        @if ($footerMenu)
            <nav aria-labelledby="footer-links" data-reveal style="--reveal-delay: 180ms">
                <h2 id="footer-links" class="font-sans text-[13px] font-bold tracking-[0.14em] text-gold uppercase">{{ __('Useful links') }}</h2>
                <ul class="mt-5 space-y-3 text-[15px]">
                    @foreach ($footerMenu as $item)
                        @if ($item['url'])
                            <li><a href="{{ $item['url'] }}" @if ($item['new_tab']) target="_blank" rel="noopener" @endif class="link-underline transition-colors hover:text-white">{{ $item['label'] }}</a></li>
                        @endif
                    @endforeach
                </ul>
            </nav>
        @endif

        <div class="col-span-2 lg:col-span-1" data-reveal style="--reveal-delay: 270ms">
            <h2 class="font-sans text-[13px] font-bold tracking-[0.14em] text-gold uppercase">{{ __('Contact us') }}</h2>
            <ul class="mt-5 space-y-4 text-[15px]">
                @if ($address = $site->address())
                    <li class="flex gap-3">
                        <x-site.icon name="map-pin" class="mt-0.5 size-5 shrink-0 text-gold" />
                        <address class="not-italic">{{ $address }}</address>
                    </li>
                @endif
                @if ($phones)
                    <li class="flex gap-3">
                        <x-site.icon name="phone" class="mt-0.5 size-5 shrink-0 text-gold" />
                        <span class="flex flex-col">
                            @foreach ($phones as $phone)
                                <a href="{{ $phone['href'] }}" class="transition-colors hover:text-white">{{ $phone['display'] }}</a>
                            @endforeach
                        </span>
                    </li>
                @endif
                @if ($emails)
                    <li class="flex gap-3">
                        <x-site.icon name="mail" class="mt-0.5 size-5 shrink-0 text-gold" />
                        <span class="flex min-w-0 flex-col">
                            @foreach ($emails as $email)
                                <a href="mailto:{{ $email }}" class="break-all transition-colors hover:text-white">{{ $email }}</a>
                            @endforeach
                        </span>
                    </li>
                @endif
            </ul>
        </div>
    </div>

    <div class="relative border-t border-white/10">
        <div class="container-site flex flex-col gap-3 py-6 text-[13px] text-white/50 sm:flex-row sm:items-center sm:justify-between" data-reveal>
            <p>© {{ now()->year }} {{ $site->name() }}. {{ __('All rights reserved.') }}</p>
            {{-- The language switcher lives in the header only. --}}
            <p class="text-white/70">
                {{ __('Designed and Developed by') }}
                <a href="https://codevioso.com" target="_blank" rel="noopener" class="font-semibold text-gold underline-offset-4 hover:underline">Codevioso</a>
            </p>
        </div>
    </div>
</footer>
