@props(['variant' => 'solid'])
@inject('site', App\Support\SiteContext::class)
@inject('branding', App\Support\Branding::class)
@php
    $menu = $site->menu('primary');
    $phones = $site->phones();
    $languages = $site->languages();
    $current = rtrim(request()->url(), '/');
    $home = rtrim(url('/'), '/');

    $isActive = function (array $item) use (&$isActive, $current, $home): bool {
        $url = rtrim((string) $item['url'], '/');

        if ($url !== '' && ($url === $current || ($url !== $home && str_starts_with($current.'/', $url.'/')))) {
            return true;
        }

        return collect($item['children'])->contains(fn ($child) => $isActive($child));
    };

    $overlay = $variant === 'overlay';
@endphp

<div x-data="siteHeader" @keydown.escape.window="closeMenu()" class="contents">
    {{-- Main header --}}
    <header
        data-transparent="{{ $overlay ? 'true' : 'false' }}"
        :data-transparent="(!scrolled && {{ $overlay ? 'true' : 'false' }}).toString()"
        @class([
            'group/header sticky top-0 z-30 border-b transition-[background-color,box-shadow,border-color] duration-300',
            'border-line/70 bg-white/92 backdrop-blur-md' => ! $overlay,
            'border-transparent -mb-[calc(5rem+1px)]' => $overlay,
        ])
        :class="scrolled && '!border-line/70 !bg-white/92 shadow-[0_8px_30px_-18px_rgb(13_27_46/0.35)] backdrop-blur-md'"
    >
        <div class="container-site flex h-20 items-center justify-between gap-6" x-ref="bar">
            {{--
                Over a dark hero the light logo shows; once the header turns white on scroll it
                crossfades to the full-colour logo. A custom logo without a light version sits
                on a white plate over the hero instead.
            --}}
            @php($light = $overlay ? $branding->lightLogo() : null)
            <a href="{{ url('/') }}" @class([
                'relative shrink-0',
                '-ml-1.5 rounded-xl px-1.5 py-0.5 transition-[background-color,box-shadow] duration-300 group-data-[transparent=true]/header:bg-white group-data-[transparent=true]/header:shadow-[0_10px_30px_-12px_rgb(0_0_0/0.45)]' => ! $light,
            ]) aria-label="{{ $site->name() }} – {{ __('Home') }}">
                <img
                    src="{{ $branding->logoUrl() }}"
                    srcset="{{ $branding->logoSrcset() }}"
                    sizes="(min-width: 1280px) 224px, (min-width: 640px) 204px, 174px"
                    alt="{{ $site->name() }}"
                    width="224"
                    height="72"
                    @class([
                        'h-[38px] w-auto min-[360px]:h-[46px] min-[380px]:h-[56px] sm:h-[66px] lg:h-[58px] xl:h-[72px]',
                        'logo-full transition-opacity duration-300 group-data-[transparent=true]/header:opacity-0 motion-reduce:transition-none' => $light,
                    ])
                    @unless ($light) fetchpriority="high" @endunless
                >
                @if ($light)
                    <img
                        src="{{ $light['src'] }}"
                        srcset="{{ $light['srcset'] }}"
                        sizes="(min-width: 1280px) 224px, (min-width: 640px) 204px, 174px"
                        alt=""
                        width="224"
                        height="72"
                        class="logo-light pointer-events-none absolute inset-0 size-full object-contain object-left opacity-0 drop-shadow-[0_2px_10px_rgb(0_0_0/0.35)] transition-opacity duration-300 group-data-[transparent=true]/header:opacity-100 motion-reduce:transition-none"
                        fetchpriority="high"
                        aria-hidden="true"
                    >
                @endif
            </a>

            {{-- Desktop navigation --}}
            {{-- Long menus on laptop widths fall back to the menu button instead of pushing the page sideways (siteHeader.fitMenu()). --}}
            <nav class="hidden lg:block" :class="fit === 2 && 'lg:!hidden'" aria-label="{{ __('Main') }}">
                <ul class="flex items-center gap-0.5 xl:gap-1">
                    @foreach ($menu as $item)
                        @php($active = $isActive($item))
                        <li class="relative" @if ($item['children']) x-data="dropdown" @mouseenter="show()" @mouseleave="hide()" @focusout="$el.contains($event.relatedTarget) || hide(0)" @endif>
                            @if ($item['children'])
                                <button
                                    type="button"
                                    @click="toggle()"
                                    :aria-expanded="open.toString()"
                                    @class([
                                        'group flex items-center gap-1 rounded-full px-2 py-2 xl:px-3 text-[14px] xl:text-[15px] whitespace-nowrap font-medium transition-colors',
                                        'text-brand' => $active,
                                    ])
                                    :class="!scrolled && {{ $overlay ? 'true' : 'false' }} ? 'text-white hover:text-gold' : '{{ $active ? 'text-brand' : 'text-ink-2 hover:text-brand' }}'"
                                >
                                    {{ $item['label'] }}
                                    <x-site.icon name="chevron-down" class="size-4 transition-transform duration-200" ::class="open && 'rotate-180'" />
                                </button>

                                <div
                                    x-cloak
                                    x-show="open"
                                    x-transition:enter="transition duration-200 ease-out"
                                    x-transition:enter-start="opacity-0 translate-y-1"
                                    x-transition:leave="transition duration-120 ease-in"
                                    x-transition:leave-end="opacity-0"
                                    class="absolute top-full left-1/2 z-40 w-64 -translate-x-1/2 pt-3"
                                >
                                    <ul class="card-site p-2 shadow-[var(--shadow-lift)]">
                                        @foreach ($item['children'] as $child)
                                            <li>
                                                @if ($child['url'])
                                                    <a href="{{ $child['url'] }}" @if ($child['new_tab']) target="_blank" rel="noopener" @endif
                                                       @class(['flex items-center justify-between rounded-xl px-3.5 py-2.5 text-[15px] transition-colors hover:bg-sand-100', 'bg-sand-100 font-semibold text-brand' => $isActive($child), 'text-ink-2 hover:text-brand' => ! $isActive($child)])>
                                                        {{ $child['label'] }}
                                                    </a>
                                                @else
                                                    <p class="px-3.5 pt-3 pb-1 text-[12px] font-bold tracking-wider text-ink-3 uppercase">{{ $child['label'] }}</p>
                                                @endif
                                                @if ($child['children'])
                                                    <ul class="mb-1 ml-3.5 border-l border-line pl-2">
                                                        @foreach ($child['children'] as $grandchild)
                                                            <li>
                                                                <a href="{{ $grandchild['url'] ?? '#' }}" @if ($grandchild['new_tab']) target="_blank" rel="noopener" @endif
                                                                   class="block rounded-lg px-3 py-2 text-[14px] text-ink-3 transition-colors hover:bg-sand-100 hover:text-brand">
                                                                    {{ $grandchild['label'] }}
                                                                </a>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @else
                                <a
                                    href="{{ $item['url'] ?? '#' }}"
                                    @if ($item['new_tab']) target="_blank" rel="noopener" @endif
                                    @if ($active) aria-current="page" @endif
                                    class="relative block rounded-full px-2 py-2 xl:px-3 text-[14px] xl:text-[15px] whitespace-nowrap font-medium transition-colors"
                                    :class="!scrolled && {{ $overlay ? 'true' : 'false' }} ? 'text-white hover:text-gold' : '{{ $active ? 'text-brand' : 'text-ink-2 hover:text-brand' }}'"
                                >
                                    {{ $item['label'] }}
                                    @if ($active)
                                        <span class="absolute inset-x-3.5 -bottom-0.5 h-0.5 rounded-full bg-gold" aria-hidden="true"></span>
                                    @endif
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="flex items-center gap-2 sm:gap-3">
                @if (count($languages) > 1)
                    <x-site.language-switcher :languages="$languages" tone="header" />
                @endif
                @if ($phones)
                    <a href="{{ $phones[0]['href'] }}" class="btn btn-gold btn-sm hidden sm:inline-flex" aria-label="{{ __('Call us') }}: {{ $phones[0]['display'] }}">
                        <x-site.icon name="phone" />
                        {{-- The number shows when the full menu leaves room for it (fit 0, see siteHeader). --}}
                        <span class="hidden xl:inline" :class="fit >= 1 && '!hidden'">{{ $phones[0]['display'] }}</span>
                        {{-- Icon only beside the full menu on small laptops, where the text does not fit. --}}
                        <span class="lg:max-xl:sr-only xl:hidden">{{ __('Call us') }}</span>
                    </a>
                @endif
                <button
                    type="button"
                    class="-mr-2 flex size-11 items-center justify-center rounded-full transition-colors lg:hidden"
                    :class="[!scrolled && {{ $overlay ? 'true' : 'false' }} ? 'text-white hover:bg-white/10' : 'text-brand hover:bg-sand-100', fit === 2 && 'lg:!flex']"
                    x-ref="menuButton"
                    @click="openMenu($el)"
                    aria-controls="mobile-menu"
                    :aria-expanded="mobileOpen.toString()"
                >
                    <span class="sr-only">{{ __('Open menu') }}</span>
                    <x-site.icon name="menu" class="size-6" />
                </button>
            </div>
        </div>
    </header>

    {{--
        Mobile menu: a "portal" that opens from the menu button. Three discs
        (gold, blue, navy) grow from the button one after another for depth,
        then the pattern and emblem drift in and the links rise one by one.
        Closing plays it in reverse. Only transform and opacity animate, so the
        phone's GPU runs it even while the page is busy. Styles: .mm-* in
        resources/css/app.css.
    --}}
    <div
        id="mobile-menu"
        class="mm lg:hidden"
        :class="[mobileOpen && 'is-open', fit === 2 && 'lg:!block']"
        :style="origin"
        role="dialog"
        aria-modal="true"
        aria-label="{{ __('Menu') }}"
        inert
        :inert="!mobileOpen"
    >
        <div class="mm-disc mm-disc-gold" aria-hidden="true"></div>
        <div class="mm-disc mm-disc-blue" aria-hidden="true"></div>
        <div class="mm-disc mm-disc-main" aria-hidden="true"></div>

        <div class="mm-panel" x-trap.noscroll="mobileOpen">
            <div class="mm-pattern pattern-star" aria-hidden="true"></div>
            <img src="{{ $branding->iconUrl(512) }}" alt="" class="mm-emblem" width="512" height="512" loading="lazy" aria-hidden="true">
            <div class="mm-glow" aria-hidden="true"></div>

            <div class="relative flex h-full flex-col">
                {{-- Top bar --}}
                <div class="mm-fade container-site flex h-20 shrink-0 items-center justify-between" style="--d: 380ms">
                    <a href="{{ url('/') }}" aria-label="{{ $site->name() }} – {{ __('Home') }}">
                        @php($menuLogo = $branding->lightLogo())
                        <img src="{{ $menuLogo['src'] ?? $branding->logoUrl() }}" srcset="{{ $menuLogo['srcset'] ?? $branding->logoSrcset() }}" sizes="170px" alt="{{ $site->name() }}" @class(['h-[54px] w-auto', 'rounded-lg bg-white px-1.5 py-0.5' => ! $menuLogo]) loading="lazy">
                    </a>
                    <button type="button" class="mm-close" @click="closeMenu()">
                        <span class="sr-only">{{ __('Close menu') }}</span>
                        <x-site.icon name="x" class="size-6" />
                    </button>
                </div>

                {{-- Links --}}
                <nav class="container-site flex-1 overflow-y-auto overscroll-contain pt-6 pb-8" aria-label="{{ __('Main') }}">
                    <p class="mm-fade mb-5 text-[12px] font-semibold tracking-[0.2em] text-gold/80 uppercase" style="--d: 420ms">{{ __('Menu') }}</p>
                    <ol class="space-y-1">
                        @foreach ($menu as $index => $item)
                            @php($active = $isActive($item))
                            <li class="mm-item" style="--i: {{ $index }}" @if ($item['children']) x-data="{ open: {{ $active ? 'true' : 'false' }} }" @endif>
                                <div class="mm-mask">
                                    @if ($item['children'])
                                        <button type="button" @click="open = !open" :aria-expanded="open.toString()" @class(['mm-link mm-rise w-full', 'is-active' => $active])>
                                            <span class="mm-index" aria-hidden="true">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                            <span class="flex-1 text-left">{{ $item['label'] }}</span>
                                            <span class="mm-toggle" :class="open && 'is-open'" aria-hidden="true"></span>
                                        </button>
                                    @else
                                        <a href="{{ $item['url'] ?? '#' }}" @if ($item['new_tab']) target="_blank" rel="noopener" @endif @if ($active) aria-current="page" @endif
                                           @class(['mm-link mm-rise', 'is-active' => $active])>
                                            <span class="mm-index" aria-hidden="true">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                            <span>{{ $item['label'] }}</span>
                                        </a>
                                    @endif
                                </div>
                                @if ($item['children'])
                                    <ul x-show="open" x-collapse @unless ($active) x-cloak @endunless class="mt-1 mb-3 ml-[2.6rem] space-y-0.5 border-l border-gold/30 pl-4">
                                        @foreach ($item['children'] as $child)
                                            <li>
                                                <a href="{{ $child['url'] ?? '#' }}" @if ($child['new_tab']) target="_blank" rel="noopener" @endif
                                                   @class(['block py-2 text-[17px] transition-colors', 'font-semibold text-gold' => $isActive($child), 'text-white/75 hover:text-white' => ! $isActive($child)])>
                                                    {{ $child['label'] }}
                                                </a>
                                                @foreach ($child['children'] as $grandchild)
                                                    <a href="{{ $grandchild['url'] ?? '#' }}" class="block py-1.5 pl-4 text-[15px] text-white/55 hover:text-white">{{ $grandchild['label'] }}</a>
                                                @endforeach
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </nav>

                {{-- Dock --}}
                <div class="mm-fade shrink-0 border-t border-white/10 bg-brand-950/85" style="--d: 560ms">
                    <div class="container-site py-5" style="padding-bottom: max(1.25rem, env(safe-area-inset-bottom))">
                        <div class="grid grid-cols-2 gap-3">
                            @if ($phones)
                                <a href="{{ $phones[0]['href'] }}" class="btn btn-gold"><x-site.icon name="phone" /> {{ __('Call') }}</a>
                            @endif
                            @if ($whatsapp = $site->whatsappUrl())
                                <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="btn btn-whatsapp"><x-site.icon name="whatsapp" /> WhatsApp</a>
                            @endif
                        </div>
                        <a href="{{ route('bookings.status') }}" class="mt-4 flex items-center justify-center gap-2 text-[14px] font-semibold text-white/80 hover:text-gold">
                            <x-site.icon name="ticket" class="size-4 text-gold" /> {{ __('Already booked? Find your booking') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
