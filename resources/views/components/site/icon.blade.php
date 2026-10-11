@props(['name'])
@php
    $filled = isset(App\Support\Icons::FILLED[$name]);
    $paths = $filled ? App\Support\Icons::FILLED[$name] : (App\Support\Icons::STROKE[$name] ?? '');
@endphp
@if ($filled)
    <svg {{ $attributes->merge(['class' => 'size-5', 'aria-hidden' => 'true']) }} viewBox="0 0 24 24" fill="currentColor">{!! $paths !!}</svg>
@else
    <svg {{ $attributes->merge(['class' => 'size-5', 'aria-hidden' => 'true']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">{!! $paths !!}</svg>
@endif
