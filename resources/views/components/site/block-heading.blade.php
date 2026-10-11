@props(['c', 'align' => 'center', 'as' => 'h2'])
{{-- Eyebrow, title and intro of a page-builder section, colored by the section palette. --}}
@if (filled($c['eyebrow'] ?? null) || filled($c['title'] ?? null) || filled($c['intro'] ?? null))
    <div {{ $attributes->class(['max-w-2xl', 'mx-auto text-center' => $align === 'center']) }} data-reveal>
        @if (filled($c['eyebrow'] ?? null))
            <p @class(['eyebrow', 'justify-center' => $align === 'center'])>{{ $c['eyebrow'] }}</p>
        @endif
        @if (filled($c['title'] ?? null))
            <{{ $as }} @class(['sec-heading text-display-lg', 'mt-4' => filled($c['eyebrow'] ?? null)])>{{ $c['title'] }}</{{ $as }}>
        @endif
        @if (filled($c['intro'] ?? null))
            <p class="sec-text mt-5 text-[1.0625rem] leading-relaxed">{{ $c['intro'] }}</p>
        @endif
    </div>
@endif
