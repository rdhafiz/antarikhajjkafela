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

<div x-data="siteHeader" @keydown.escape.window="mobileOpen = false" class="contents">
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
        <div class="container-site flex h-20 items-center justify-between gap-6">
            <a href="{{ url('/') }}" class="relative shrink-0" aria-label="{{ $site->name() }} – {{ __('Home') }}">
                <img
                    src="{{ $branding->logoUrl() }}"
                    srcset="{{ $branding->logoSrcset() }}"
                    sizes="180px"
                    alt="{{ $site->name() }}"
                    width="180"
                    height="61"
                    class="h-auto w-[150px] sm:w-[172px]"
                    fetchpriority="high"
                >
            </a>

            {{-- Desktop navigation --}}
            <nav class="hidden lg:block" aria-label="{{ __('Main') }}">
                <ul class="flex items-center gap-1 xl:gap-2">
                    @foreach ($menu as $item)
                        @php($active = $isActive($item))
                        <li class="relative" @if ($item['children']) x-data="dropdown" @mouseenter="show()" @mouseleave="hide()" @focusout="$el.contains($event.relatedTarget) || hide(0)" @endif>
                            @if ($item['children'])
                                <button
                                    type="button"
                                    @click="toggle()"
                                    :aria-expanded="open.toString()"
                                    @class([
                                        'group flex items-center gap-1 rounded-full px-3.5 py-2 text-[15px] font-medium transition-colors',
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
                                    class="relative block rounded-full px-3.5 py-2 text-[15px] font-medium transition-colors"
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
                    <a href="{{ $phones[0]['href'] }}" class="btn btn-gold btn-sm hidden sm:inline-flex">
                        <x-site.icon name="phone" />
                        <span class="hidden xl:inline">{{ $phones[0]['display'] }}</span>
                        <span class="xl:hidden">{{ __('Call us') }}</span>
                    </a>
                @endif
                <button
                    type="button"
                    class="-mr-2 flex size-11 items-center justify-center rounded-full transition-colors lg:hidden"
                    :class="!scrolled && {{ $overlay ? 'true' : 'false' }} ? 'text-white hover:bg-white/10' : 'text-brand hover:bg-sand-100'"
                    @click="mobileOpen = true"
                    aria-controls="mobile-menu"
                    :aria-expanded="mobileOpen.toString()"
                >
                    <span class="sr-only">{{ __('Open menu') }}</span>
                    <x-site.icon name="menu" class="size-6" />
                </button>
            </div>
        </div>
    </header>

    {{-- Mobile menu --}}
    <div x-cloak x-show="mobileOpen" class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="{{ __('Menu') }}">
        <div
            x-show="mobileOpen"
            x-transition.opacity.duration.250ms
            class="absolute inset-0 bg-brand-950/55 backdrop-blur-sm"
            @click="mobileOpen = false"
        ></div>

        <div
            id="mobile-menu"
            x-show="mobileOpen"
            x-trap.noscroll="mobileOpen"
            x-transition:enter="transition duration-300 ease-out"
            x-transition:enter-start="translate-x-full"
            x-transition:leave="transition duration-200 ease-in"
            x-transition:leave-end="translate-x-full"
            class="absolute inset-y-0 right-0 flex w-full max-w-sm flex-col bg-sand-50 shadow-2xl"
        >
            <div class="flex h-20 items-center justify-between border-b border-line px-5">
                <img src="{{ $branding->logoUrl() }}" srcset="{{ $branding->logoSrcset() }}" sizes="140px" alt="{{ $site->name() }}" class="h-auto w-[140px]" loading="lazy">
                <button type="button" class="-mr-2 flex size-11 items-center justify-center rounded-full text-brand hover:bg-sand-100" @click="mobileOpen = false">
                    <span class="sr-only">{{ __('Close menu') }}</span>
                    <x-site.icon name="x" class="size-6" />
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto px-3 py-4" aria-label="{{ __('Main') }}">
                <ul class="space-y-0.5">
                    @foreach ($menu as $item)
                        <li @if ($item['children']) x-data="{ open: {{ $isActive($item) ? 'true' : 'false' }} }" @endif>
                            @if ($item['children'])
                                <button type="button" @click="open = !open" :aria-expanded="open.toString()"
                                        @class(['flex w-full items-center justify-between rounded-xl px-4 py-3.5 text-left text-[17px] font-medium', 'text-brand' => $isActive($item), 'text-ink' => ! $isActive($item)])>
                                    {{ $item['label'] }}
                                    <x-site.icon name="chevron-down" class="size-5 text-ink-3 transition-transform" ::class="open && 'rotate-180'" />
                                </button>
                                <ul x-show="open" x-collapse class="mb-2 ml-4 border-l-2 border-gold/60 pl-2">
                                    @foreach ($item['children'] as $child)
                                        <li>
                                            <a href="{{ $child['url'] ?? '#' }}" @if ($child['new_tab']) target="_blank" rel="noopener" @endif
                                               @class(['block rounded-lg px-4 py-2.5 text-[16px]', 'font-semibold text-brand' => $isActive($child), 'text-ink-2' => ! $isActive($child)])>
                                                {{ $child['label'] }}
                                            </a>
                                            @foreach ($child['children'] as $grandchild)
                                                <a href="{{ $grandchild['url'] ?? '#' }}" class="block rounded-lg py-2 pr-4 pl-8 text-[15px] text-ink-3">{{ $grandchild['label'] }}</a>
                                            @endforeach
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <a href="{{ $item['url'] ?? '#' }}" @if ($item['new_tab']) target="_blank" rel="noopener" @endif
                                   @if ($isActive($item)) aria-current="page" @endif
                                   @class(['flex items-center justify-between rounded-xl px-4 py-3.5 text-[17px] font-medium', 'bg-white text-brand shadow-[var(--shadow-soft)]' => $isActive($item), 'text-ink' => ! $isActive($item)])>
                                    {{ $item['label'] }}
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="space-y-3 border-t border-line bg-white px-5 py-5">
                <div class="grid grid-cols-2 gap-2.5">
                    @if ($phones)
                        <a href="{{ $phones[0]['href'] }}" class="btn btn-primary"><x-site.icon name="phone" /> {{ __('Call') }}</a>
                    @endif
                    @if ($whatsapp = $site->whatsappUrl())
                        <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="btn btn-whatsapp"><x-site.icon name="whatsapp" /> WhatsApp</a>
                    @endif
                </div>
                @if (count($languages) > 1)
                    <div class="flex items-center justify-between pt-1">
                        <span class="text-[13px] font-semibold text-ink-3">{{ __('Language') }}</span>
                        <x-site.language-switcher :languages="$languages" tone="light" />
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
