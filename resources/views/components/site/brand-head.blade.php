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
@if ($favicon = $branding->favicon())
    <link rel="icon" type="image/webp" sizes="32x32" href="{{ $favicon->url('32') }}">
    <link rel="icon" type="image/webp" sizes="192x192" href="{{ $favicon->url('192') }}">
@else
    <link rel="icon" href="{{ asset('favicon.ico') }}">
@endif
@if ($appIcon = $branding->appIcon())
    <link rel="apple-touch-icon" href="{{ $appIcon->url('192') }}">
@endif
<link rel="manifest" href="{{ route('manifest') }}">
