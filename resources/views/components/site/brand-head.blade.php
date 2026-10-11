@inject('branding', App\Support\Branding::class)
@php($colors = $branding->colors())
<meta name="theme-color" content="{{ $colors['primary'] }}">
<style>
    :root {
        --brand-primary: {{ $colors['primary'] }};
        --brand-secondary: {{ $colors['secondary'] }};
        --brand-accent: {{ $colors['accent'] }};
    }
</style>
<link rel="icon" type="image/webp" sizes="32x32" href="{{ $branding->faviconUrl(32) }}">
<link rel="icon" type="image/webp" sizes="192x192" href="{{ $branding->faviconUrl(192) }}">
<link rel="apple-touch-icon" href="{{ $branding->iconUrl(192) }}">
<link rel="manifest" href="{{ route('manifest') }}">
