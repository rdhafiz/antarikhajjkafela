{{--
    Booking details for emails: a soft card with what was booked, then the amounts as coloured tiles.
    Expects $booking; $locale optional; $amounts false leaves prices out (the status is in the heading's pill).
--}}
@php
    use App\Domain\Travel\PackagePresenter;
    use App\Support\Money;

    $locale ??= app()->getLocale();
    // Customers of a package that hides its price see no amounts except what they paid.
    $amounts ??= ! $booking->hidesPrice();
    $rows = array_filter([
        __('Reference', [], $locale) => $booking->reference,
        __('Package', [], $locale) => $booking->snap('package', $locale),
        __('Option', [], $locale) => trim($booking->snap('type', $locale).' – '.$booking->snap('option', $locale), ' –'),
        __('Travelers', [], $locale) => Money::digits($booking->travelers, $locale),
        __('Travel', [], $locale) => App\Domain\Booking\TravelDates::label($booking->travel_month, $booking->travel_year, $locale),
        __('Price', [], $locale) => $amounts ? Money::format($booking->unit_price, $locale).' '.PackagePresenter::unitLabel($booking->snapshot['unit'] ?? 'person') : __('Price on request', [], $locale),
    ], fn ($value) => filled($value));
    // Amount tiles: label, value, background, accent.
    $tiles = array_values(array_filter([
        $amounts ? [__('Total', [], $locale), Money::format($booking->total, $locale), '#eaf3ff', '#012a51'] : null,
        $amounts && (float) $booking->advance_amount > 0 ? [__('Advance', [], $locale), Money::format($booking->advance_amount, $locale), '#f1edfd', '#5b21b6'] : null,
        $amounts || (float) $booking->paid_amount > 0 ? [__('Paid', [], $locale), Money::format($booking->paid_amount, $locale), '#e6f6ed', '#047857'] : null,
        $amounts ? [__('Balance', [], $locale), Money::format($booking->balance(), $locale), '#fff1e3', '#9a3412'] : null,
    ]));
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f8fc;border:1px solid #e3eaf3;border-radius:14px;">
    <tr><td style="padding:6px 18px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">
            @foreach ($rows as $label => $value)
                <tr>
                    <td style="padding:10px 0;color:#66738a;width:38%;vertical-align:top;{{ $loop->last ? '' : 'border-bottom:1px solid #e3eaf3;' }}">{{ $label }}</td>
                    <td style="padding:10px 0;font-weight:bold;color:#0d1b2e;vertical-align:top;{{ $loop->last ? '' : 'border-bottom:1px solid #e3eaf3;' }}">{{ $value }}</td>
                </tr>
            @endforeach
        </table>
    </td></tr>
</table>

@if ($tiles)
    {{-- Two tiles per row, so the figures stay readable on a phone. --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:12px;">
        @foreach (array_chunk($tiles, 2) as $pair)
            <tr>
                @foreach ($pair as $i => [$label, $value, $bg, $accent])
                    <td width="50%" style="padding:{{ $loop->parent->first ? '0' : '10px' }} {{ $i === 0 ? '5px' : '0' }} 0 {{ $i === 1 ? '5px' : '0' }};vertical-align:top;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{{ $bg }};border-radius:12px;">
                            <tr><td style="padding:12px 14px;">
                                <p style="margin:0;font-size:11px;font-weight:bold;letter-spacing:0.6px;text-transform:uppercase;color:{{ $accent }};">{{ $label }}</p>
                                <p style="margin:4px 0 0;font-size:18px;font-weight:bold;color:#0d1b2e;">{{ $value }}</p>
                            </td></tr>
                        </table>
                    </td>
                @endforeach
                @if (count($pair) === 1)<td width="50%"></td>@endif
            </tr>
        @endforeach
    </table>
@endif
