{{-- Booking details table for emails. Expects $booking; $locale optional. --}}
@php
    use App\Domain\Travel\PackagePresenter;
    use App\Support\Money;

    $locale ??= app()->getLocale();
    $rows = array_filter([
        __('Reference', [], $locale) => $booking->reference,
        __('Package', [], $locale) => $booking->snap('package', $locale),
        __('Option', [], $locale) => trim($booking->snap('type', $locale).' – '.$booking->snap('option', $locale), ' –'),
        __('Travelers', [], $locale) => Money::digits($booking->travelers, $locale),
        __('Price', [], $locale) => Money::format($booking->unit_price, $locale).' '.PackagePresenter::unitLabel($booking->snapshot['unit'] ?? 'person'),
        __('Total', [], $locale) => Money::format($booking->total, $locale),
        __('Advance', [], $locale) => (float) $booking->advance_amount > 0 ? Money::format($booking->advance_amount, $locale) : null,
        __('Paid', [], $locale) => Money::format($booking->paid_amount, $locale),
        __('Balance', [], $locale) => Money::format($booking->balance(), $locale),
    ], fn ($value) => filled($value));
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;border-top:1px solid #ece4d6;">
    @foreach ($rows as $label => $value)
        <tr>
            <td style="padding:9px 0;color:#66738a;width:40%;border-bottom:1px solid #ece4d6;">{{ $label }}</td>
            <td style="padding:9px 0;font-weight:bold;border-bottom:1px solid #ece4d6;">{{ $value }}</td>
        </tr>
    @endforeach
</table>
