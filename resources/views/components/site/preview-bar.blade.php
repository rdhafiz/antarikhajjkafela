@props(['state' => 'draft', 'label', 'editUrl' => null])
{{-- Staff preview of a draft/scheduled page or post: a floating pill that never covers the header or the hero text. --}}
@php
    $dot = match ($state) {
        'published', 'live' => 'bg-emerald-400',
        'scheduled' => 'bg-sky-400',
        default => 'bg-gold',
    };
@endphp
<div class="pointer-events-none fixed inset-x-4 bottom-4 z-[60] flex justify-start sm:inset-x-0 sm:bottom-6 sm:justify-center" style="margin-bottom: env(safe-area-inset-bottom)">
    <div class="pointer-events-auto flex max-w-[calc(100%-4.5rem)] items-center gap-3 rounded-full border border-white/10 bg-brand-950/90 py-1.5 ps-4 pe-1.5 text-[13px] text-white shadow-[0_18px_40px_-12px_rgb(2_12_27/0.6)] backdrop-blur-md sm:max-w-none" role="status">
        <span class="relative flex size-2.5 shrink-0" aria-hidden="true">
            @if ($state !== 'published' && $state !== 'live')
                <span class="absolute inset-0 rounded-full {{ $dot }} opacity-60 motion-safe:animate-ping"></span>
            @endif
            <span class="relative size-2.5 rounded-full {{ $dot }}"></span>
        </span>
        <span class="min-w-0 leading-tight">
            <span class="font-bold">{{ __('Preview') }}</span>
            <span class="text-white/70">· {{ $label }}</span>
        </span>
        @if ($editUrl)
            <a href="{{ $editUrl }}" class="shrink-0 rounded-full bg-white/10 px-3.5 py-1.5 font-semibold text-white transition-colors hover:bg-white/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold">
                {{ __('Edit') }}
            </a>
        @endif
    </div>
</div>
