@props([
    'href' => null,
    'variant' => 'primary',   // primary | gold | outline | outline-light | whatsapp
    'size' => 'md',           // sm | md | lg
    'icon' => null,           // icon before the label
    'iconRight' => null,      // icon after the label
    'external' => false,
    'type' => 'button',
])
@php
    $classes = 'btn btn-'.$variant.($size !== 'md' ? ' btn-'.$size : '').($iconRight ? ' group' : '');
@endphp
@if ($href)
    <a href="{{ $href }}" @if ($external) target="_blank" rel="noopener" @endif {{ $attributes->class($classes) }}>
        @if ($icon)<x-site.icon :name="$icon" />@endif
        {{ $slot }}
        @if ($iconRight)<x-site.icon :name="$iconRight" class="transition-transform duration-200 group-hover:translate-x-0.5" />@endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-site.icon :name="$icon" />@endif
        {{ $slot }}
        @if ($iconRight)<x-site.icon :name="$iconRight" />@endif
    </button>
@endif
