@props(['link' => false])
{{-- Shown in place of a package price when the package hides it; with link, it calls the office. --}}
@inject('site', App\Support\SiteContext::class)
@php($phone = $link ? ($site->phones()[0] ?? null) : null)
@if ($phone)
    <a href="{{ $phone['href'] }}" {{ $attributes->class('inline-flex items-center gap-[0.3em] hover:underline') }}>
        <x-site.icon name="phone" class="size-[0.85em] shrink-0" />{{ __('Price on request') }}<span class="sr-only"> · {{ __('Call us') }}</span>
    </a>
@else
    <span {{ $attributes }}>{{ __('Price on request') }}</span>
@endif
