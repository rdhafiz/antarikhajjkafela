@props(['variant' => 'solid'])
@inject('site', App\Support\SiteContext::class)
@inject('branding', App\Support\Branding::class)
@php($phones = $site->phones())
{{-- Slim header for campaign landing pages: logo, language and a call button only. --}}
<header @class([
    'relative z-30',
    'border-b border-line/70 bg-white' => $variant !== 'overlay',
    '-mb-20 bg-transparent' => $variant === 'overlay',
])>
    <div class="container-site flex h-20 items-center justify-between gap-4">
        <a href="{{ url('/') }}" aria-label="{{ $site->name() }}">
            @php($light = $variant === 'overlay' ? $branding->lightLogo() : null)
            <img src="{{ $light['src'] ?? $branding->logoUrl() }}" srcset="{{ $light['srcset'] ?? $branding->logoSrcset() }}" sizes="200px" alt="{{ $site->name() }}" width="200" height="62" class="h-[52px] w-auto sm:h-[60px]">
        </a>
        <div class="flex items-center gap-3">
            @if (count($languages = $site->languages()) > 1)
                <x-site.language-switcher :languages="$languages" :tone="$variant === 'overlay' ? 'dark' : 'light'" />
            @endif
            @if ($phones)
                <a href="{{ $phones[0]['href'] }}" class="btn btn-gold btn-sm">
                    <x-site.icon name="phone" />
                    <span class="hidden sm:inline">{{ $phones[0]['display'] }}</span>
                </a>
            @endif
        </div>
    </div>
</header>
