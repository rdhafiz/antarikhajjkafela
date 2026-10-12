@props(['variant' => 0])
{{--
    Flat vector portrait used when a team member has no photo. Five generic looks
    (skin tone, beard, cap or turban, clothes, background) chosen by $variant, so a
    row of placeholders does not repeat. Decorative only: the name is shown beside it.
--}}
@php
    $looks = [
        ['bg' => '#e4ebf5', 'skin' => '#e2b48c', 'beard' => '#d6d3cf', 'hat' => 'kufi', 'hatColor' => '#ffffff', 'hatTrim' => '#d4a017', 'body' => '#ffffff', 'vest' => null, 'shawl' => null],
        ['bg' => '#f6ecd6', 'skin' => '#c99067', 'beard' => '#2b2420', 'hat' => 'turban', 'hatColor' => '#ffffff', 'hatTrim' => '#e5e0d8', 'body' => '#0b2a4a', 'vest' => null, 'shawl' => null],
        ['bg' => '#dcefe6', 'skin' => '#d9a47a', 'beard' => '#1f1b18', 'hat' => 'kufi', 'hatColor' => '#0f5132', 'hatTrim' => '#d4a017', 'body' => '#ffffff', 'vest' => '#0f5132', 'shawl' => null],
        ['bg' => '#ece6f6', 'skin' => '#b97f57', 'beard' => '#3a302a', 'hat' => 'turban', 'hatColor' => '#f3efe7', 'hatTrim' => '#d4a017', 'body' => '#ffffff', 'vest' => null, 'shawl' => '#d4a017'],
        ['bg' => '#fbefd9', 'skin' => '#e6be98', 'beard' => '#5b4a3f', 'hat' => 'cap', 'hatColor' => '#0b2a4a', 'hatTrim' => '#1d4673', 'body' => '#efe4cf', 'vest' => '#0b2a4a', 'shawl' => null],
    ];
    $l = $looks[abs((int) $variant) % count($looks)];
@endphp
<svg viewBox="14 16 172 184" preserveAspectRatio="xMidYMax slice" fill="none" aria-hidden="true" {{ $attributes->merge(['class' => 'block size-full']) }}>
    <rect width="200" height="200" fill="{{ $l['bg'] }}" />
    <circle cx="100" cy="96" r="70" fill="#ffffff" fill-opacity=".45" />

    {{-- Body and collar --}}
    <path d="M28 200c0-46 30-72 72-72s72 26 72 72z" fill="{{ $l['body'] }}" />
    @if ($l['vest'])
        <path d="M28 200c0-40 22-63 54-70l18 70zM172 200c0-40-22-63-54-70l-18 70z" fill="{{ $l['vest'] }}" />
    @endif
    @if ($l['shawl'])
        <path d="M44 162c18-20 36-30 56-30s38 10 56 30l-10 12c-14-14-30-22-46-22s-32 8-46 22z" fill="{{ $l['shawl'] }}" />
    @endif
    <path d="M86 128l14 22 14-22" stroke="#0b2a4a" stroke-opacity=".25" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
    <rect x="90" y="106" width="20" height="24" rx="8" fill="{{ $l['skin'] }}" />

    {{-- Head --}}
    <circle cx="70" cy="86" r="7" fill="{{ $l['skin'] }}" />
    <circle cx="130" cy="86" r="7" fill="{{ $l['skin'] }}" />
    <circle cx="100" cy="82" r="31" fill="{{ $l['skin'] }}" />
    <circle cx="89" cy="81" r="3" fill="#1f2937" />
    <circle cx="111" cy="81" r="3" fill="#1f2937" />
    <path d="M84 72.5q5-3 10 0M106 72.5q5-3 10 0" stroke="{{ $l['beard'] }}" stroke-width="3" stroke-linecap="round" />

    {{-- Beard and mustache --}}
    <path d="M70 86c0 32 14 48 30 48s30-16 30-48c-7 13-17 16-30 16s-23-3-30-16z" fill="{{ $l['beard'] }}" />
    <path d="M90 98q10-5 20 0q-10 5-20 0z" fill="{{ $l['beard'] }}" />

    {{-- Headwear --}}
    @if ($l['hat'] === 'kufi')
        <path d="M68 74q0-30 32-30t32 30z" fill="{{ $l['hatColor'] }}" />
        <path d="M68 74h64" stroke="{{ $l['hatTrim'] }}" stroke-width="4" stroke-linecap="round" />
        <path d="M80 62h40M76 68h48" stroke="{{ $l['hatTrim'] }}" stroke-opacity=".55" stroke-width="2" stroke-dasharray="3 4" />
    @elseif ($l['hat'] === 'turban')
        <path d="M62 78c-6-36 82-36 76 0-10-8-22-11-38-11s-28 3-38 11z" fill="{{ $l['hatColor'] }}" />
        <path d="M66 62c16-10 52-10 68 0M64 70c18-9 54-9 72 0" stroke="{{ $l['hatTrim'] }}" stroke-width="2.5" stroke-linecap="round" />
        <path d="M130 70c10 6 12 18 8 30-2-8-6-14-12-18z" fill="{{ $l['hatColor'] }}" />
    @else
        <path d="M70 72q0-24 30-24t30 24z" fill="{{ $l['hatColor'] }}" />
        <path d="M70 72h60" stroke="{{ $l['hatTrim'] }}" stroke-width="5" stroke-linecap="round" />
    @endif
</svg>
