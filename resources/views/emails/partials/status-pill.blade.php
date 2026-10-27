{{-- The booking status as a coloured pill, in the email's language. Expects $status; $locale optional. --}}
@php
    [$bg, $fg] = match ($status) {
        'pending' => ['#fff4cc', '#7a5a00'],
        'confirmed' => ['#e6efff', '#1d4ed8'],
        'partially_paid' => ['#e0f2fe', '#0369a1'],
        'advance_paid' => ['#efe9ff', '#5b21b6'],
        'fully_paid', 'completed' => ['#e3f6ec', '#047857'],
        'cancelled' => ['#fde8e8', '#b91c1c'],
        default => ['#eef3f9', '#012a51'],
    };
@endphp
<span style="display:inline-block;background:{{ $bg }};color:{{ $fg }};font-size:12px;font-weight:bold;padding:6px 12px;border-radius:999px;">&#9679;&nbsp; {{ App\Models\Booking::statusLabel($status, $locale ?? null) }}</span>
