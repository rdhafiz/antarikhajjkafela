@props([
    /** "solid" (default) or "overlay": transparent header on top of a dark hero. */
    'header' => 'solid',
    /** Focused landing layout for campaigns: slim header and footer, no menus. */
    'focused' => false,
    /** Extra tracking IDs for this page (campaigns): gtm_id, facebook_pixel_id. */
    'tracking' => [],
    /** Load analytics and ad tags. Off on private pages such as a customer's booking. */
    'analytics' => true,
    /** Show the footer's call-to-action band (hidden when the page already ends with one). */
    'footerCta' => true,
])
@php($locale = app()->getLocale())
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ collect(app(App\Support\Locales::class)->available())->get($locale)['dir'] ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    {{-- Phones, touch screens and low-memory devices get calm motion: no parallax, reveals play once. Set before the CSS so the hero never jumps. --}}
    <script>(function(n,m){if(m('(max-width: 767px)').matches||m('(pointer: coarse)').matches||4>=n.deviceMemory||4>=n.hardwareConcurrency||(n.connection&&n.connection.saveData))document.documentElement.classList.add('motion-lite')})(navigator,matchMedia)</script>

    <x-site.seo-head />
    <x-site.brand-head />

    @foreach ($locale === 'bn' ? ['noto-sans-bengali-bengali-400-700'] : ['pt-sans-caption-latin-400', 'pt-sans-caption-latin-700'] as $font)
        <link rel="preload" as="font" type="font/woff2" href="{{ Vite::asset("resources/fonts/{$font}.woff2") }}" crossorigin>
    @endforeach
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @if ($analytics)
        <x-site.tracking position="head" :extra="$tracking" />
    @endif
    {{ $head ?? '' }}
</head>
<body class="min-h-dvh">
    @if ($analytics)
        <x-site.tracking position="body" :extra="$tracking" />
    @endif

    <a href="#main" class="sr-only z-[100] rounded-full bg-gold px-5 py-2.5 font-semibold text-brand-950 focus:not-sr-only focus:fixed focus:top-3 focus:left-3">
        {{ __('Skip to content') }}
    </a>

    @if ($focused)
        <x-site.focused-header :variant="$header" />
    @else
        <x-site.header :variant="$header" />
    @endif

    <main id="main" tabindex="-1" class="outline-none">
        {{ $slot }}
    </main>

    @if ($focused)
        <x-site.focused-footer />
    @else
        <x-site.footer :cta="$footerCta" />
    @endif
    <x-site.whatsapp-button />
</body>
</html>
