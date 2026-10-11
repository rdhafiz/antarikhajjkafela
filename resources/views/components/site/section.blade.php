@props([
    'tone' => 'sand',       // sand | white | sand-deep | navy
    'spacing' => 'lg',      // sm | md | lg | none
    'pattern' => false,     // subtle star lattice in the background
    'id' => null,
])
@php
    $tones = [
        'sand' => 'bg-sand-50 text-ink',
        'white' => 'bg-white text-ink',
        'sand-deep' => 'bg-sand-100 text-ink',
        'navy' => 'bg-brand text-white',
    ];
    $spacings = [
        'none' => '',
        'sm' => 'py-12 sm:py-14',
        'md' => 'py-16 sm:py-20',
        'lg' => 'py-20 sm:py-24 lg:py-28',
    ];
@endphp
<section @if ($id) id="{{ $id }}" @endif {{ $attributes->class(['relative isolate overflow-hidden', $tones[$tone] ?? $tones['sand'], $spacings[$spacing] ?? $spacings['lg']]) }}>
    @if ($pattern)
        <div @class([
            'pointer-events-none absolute inset-0 -z-10',
            'pattern-star opacity-[0.06]' => $tone === 'navy',
            'pattern-star-ink opacity-[0.025]' => $tone !== 'navy',
        ]) aria-hidden="true"></div>
    @endif
    <div class="container-site">
        {{ $slot }}
    </div>
</section>
