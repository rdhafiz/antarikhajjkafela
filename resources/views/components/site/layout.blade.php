@props([
    /** "solid" (default) or "overlay": transparent header on top of a dark hero. */
    'header' => 'solid',
])
@php($locale = app()->getLocale())
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ collect(app(App\Support\Locales::class)->available())->get($locale)['dir'] ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <x-site.seo-head />
    <x-site.brand-head />

    @foreach ($locale === 'bn' ? [] : ['plus-jakarta-sans-latin-400', 'fraunces-latin-600'] as $font)
        <link rel="preload" as="font" type="font/woff2" href="{{ Vite::asset("resources/fonts/{$font}.woff2") }}" crossorigin>
    @endforeach
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <x-site.tracking position="head" />
    {{ $head ?? '' }}
</head>
<body class="min-h-dvh">
    <x-site.tracking position="body" />

    <a href="#main" class="sr-only z-[100] rounded-full bg-gold px-5 py-2.5 font-semibold text-brand-950 focus:not-sr-only focus:fixed focus:top-3 focus:left-3">
        {{ __('Skip to content') }}
    </a>

    <x-site.header :variant="$header" />

    <main id="main" tabindex="-1" class="outline-none">
        {{ $slot }}
    </main>

    <x-site.footer />
    <x-site.whatsapp-button />
</body>
</html>
