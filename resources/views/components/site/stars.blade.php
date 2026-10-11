@props(['rating'])
{{-- A 1-5 star rating, announced as text to screen readers. --}}
<span {{ $attributes->class('inline-flex items-center gap-0.5') }} role="img" aria-label="{{ __(':rating out of 5 stars', ['rating' => App\Support\Money::digits($rating)]) }}">
    @for ($i = 1; $i <= 5; $i++)
        <svg viewBox="0 0 24 24" aria-hidden="true" @class(['size-4', 'text-gold' => $i <= $rating, 'text-ink-3/25' => $i > $rating])>
            <path fill="currentColor" d="m12 2.8 2.8 5.8 6.3.9-4.6 4.4 1.1 6.3L12 17.2l-5.6 3 1.1-6.3L2.9 9.5l6.3-.9z"/>
        </svg>
    @endfor
</span>
