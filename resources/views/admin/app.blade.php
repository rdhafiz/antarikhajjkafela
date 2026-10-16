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
    {{-- Same icon as the website: the uploaded favicon, or the brand icon when there is none. --}}
    <link rel="icon" type="image/webp" sizes="32x32" href="{{ $branding->faviconUrl(32) }}">
    <link rel="icon" type="image/webp" sizes="192x192" href="{{ $branding->faviconUrl(192) }}">
    <link rel="apple-touch-icon" href="{{ $branding->iconUrl(192) }}">

    <link rel="preload" as="font" type="font/woff2" href="{{ Vite::asset('resources/fonts/plus-jakarta-sans-latin-400.woff2') }}" crossorigin>
    {{-- Base of the client router: the admin portal sits under /{admin_prefix}/. --}}
    <script>window.adminBase = @js('/'.trim(config('app.admin_prefix'), '/').'/');</script>
    @vite('resources/admin/main.ts')
</head>
<body class="antialiased">
    <div id="admin"></div>
    <noscript>The admin portal requires JavaScript.</noscript>
</body>
</html>
