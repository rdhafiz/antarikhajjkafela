<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Admin · {{ $appName }}</title>

    <style>
        :root {
            --admin-primary: {{ $theme['primary_color'] }};
            --admin-secondary: {{ $theme['secondary_color'] }};
            --admin-accent: {{ $theme['accent_color'] }};
            --admin-sidebar-bg: {{ $theme['sidebar_bg'] }};
            --admin-sidebar-text: {{ $theme['sidebar_text'] }};
            --admin-sidebar-accent: {{ $theme['sidebar_accent'] }};
            --admin-sidebar-logo-bg: {{ $theme['sidebar_logo_bg'] }};
            --admin-header-bg: {{ $theme['header_bg'] }};
            --admin-header-text: {{ $theme['header_text'] }};
        }
    </style>

    @inject('branding', App\Support\Branding::class)
    @if ($favicon = $branding->favicon())
        <link rel="icon" type="image/webp" href="{{ $favicon->url('32') }}">
    @endif

    <link rel="preload" as="font" type="font/woff2" href="{{ Vite::asset('resources/fonts/plus-jakarta-sans-latin-400.woff2') }}" crossorigin>
    @vite('resources/admin/main.ts')
</head>
<body class="antialiased">
    <div id="admin"></div>
    <noscript>The admin portal requires JavaScript.</noscript>
</body>
</html>
