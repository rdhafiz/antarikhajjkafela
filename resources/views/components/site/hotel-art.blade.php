@props(['variant' => 0])
{{-- A small hotel illustration for booking options; three colourways rotate across the options. --}}
@php
    $palettes = [
        // White hotel with blue glass, palms.
        ['wall' => '#f8fafc', 'side' => '#e2e8f0', 'trim' => '#1e3a8a', 'glass' => '#3b82f6', 'glow' => '#bfdbfe', 'door' => '#fbbf24', 'leaf' => '#16a34a', 'leaf2' => '#4ade80'],
        // Deep blue glass tower.
        ['wall' => '#1e3a8a', 'side' => '#172554', 'trim' => '#0f172a', 'glass' => '#60a5fa', 'glow' => '#dbeafe', 'door' => '#fcd34d', 'leaf' => '#15803d', 'leaf2' => '#22c55e'],
        // Warm sandstone.
        ['wall' => '#f4c97a', 'side' => '#e0a955', 'trim' => '#92400e', 'glass' => '#1e3a5f', 'glow' => '#fde68a', 'door' => '#7c2d12', 'leaf' => '#15803d', 'leaf2' => '#65a30d'],
    ];
    $c = $palettes[$variant % count($palettes)];
@endphp
<svg viewBox="0 0 120 120" fill="none" aria-hidden="true" {{ $attributes }}>
    <ellipse cx="60" cy="104" rx="44" ry="6" fill="#0f172a" opacity=".12" />
    {{-- Side wing --}}
    <path d="M70 46h26v58H70z" fill="{{ $c['side'] }}" />
    <path d="M68 42h30v5H68z" fill="{{ $c['trim'] }}" />
    @foreach ([52, 62, 72, 82, 92] as $y)
        <rect x="74" y="{{ $y }}" width="7" height="6" rx="1" fill="{{ $c['glass'] }}" />
        <rect x="85" y="{{ $y }}" width="7" height="6" rx="1" fill="{{ $c['glass'] }}" opacity=".85" />
    @endforeach
    {{-- Main tower --}}
    <path d="M30 26h42v78H30z" fill="{{ $c['wall'] }}" />
    <path d="M27 21h48v6H27z" fill="{{ $c['trim'] }}" />
    <path d="M44 15h14v6H44z" fill="{{ $c['trim'] }}" />
    @foreach ([32, 43, 54, 65, 76] as $y)
        @foreach ([35, 47, 59] as $x)
            <rect x="{{ $x }}" y="{{ $y }}" width="8" height="7" rx="1.2" fill="{{ $c['glass'] }}" />
            <rect x="{{ $x }}" y="{{ $y }}" width="8" height="2.4" rx="1.2" fill="{{ $c['glow'] }}" opacity=".7" />
        @endforeach
    @endforeach
    {{-- Entrance and canopy --}}
    <path d="M42 89h18v15H42z" fill="{{ $c['door'] }}" />
    <path d="M38 86h26l-3 4H41z" fill="{{ $c['trim'] }}" />
    <path d="M51 89v15" stroke="{{ $c['wall'] }}" stroke-width="1.2" />
    {{-- Trees --}}
    <path d="M20 104V92" stroke="#7c4a1e" stroke-width="2" stroke-linecap="round" />
    <circle cx="20" cy="88" r="7" fill="{{ $c['leaf'] }}" />
    <circle cx="23" cy="85" r="4.5" fill="{{ $c['leaf2'] }}" />
    <path d="M102 104V90" stroke="#7c4a1e" stroke-width="2" stroke-linecap="round" />
    <path d="M102 90c-7-1-10 2-12 5M102 90c6-2 10 0 12 4M102 90c-3-5-8-6-11-5M102 90c3-5 8-6 11-4" stroke="{{ $c['leaf'] }}" stroke-width="2.6" stroke-linecap="round" />
    <circle cx="12" cy="101" r="4" fill="{{ $c['leaf2'] }}" />
    <circle cx="94" cy="102" r="3.5" fill="{{ $c['leaf2'] }}" />
</svg>
