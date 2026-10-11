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
            <img src="{{ $branding->logoUrl() }}" srcset="{{ $branding->logoSrcset() }}" sizes="160px" alt="{{ $site->name() }}" width="160" height="54" class="h-auto w-[140px] sm:w-[160px]">
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
