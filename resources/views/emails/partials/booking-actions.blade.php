{{-- The customer's way back to the booking: the private link as a gold button, and the lookup page with reference + phone. Expects $booking. --}}
<table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:26px;">
    <tr><td style="background:#fdc802;border-radius:999px;">
        <a href="{{ $booking->publicUrl() }}" style="display:inline-block;padding:13px 26px;color:#012a51;text-decoration:none;font-weight:bold;font-size:14px;">{{ __('View your booking') }} &rarr;</a>
    </td></tr>
</table>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:18px;background:#eaf3ff;border-radius:12px;">
    <tr><td style="padding:12px 16px;font-size:13px;line-height:1.6;color:#3a485c;">
        {{ __('You can also find your booking any time with your reference number and phone number:') }}
        <a href="{{ route('bookings.status') }}" style="color:#012a51;font-weight:bold;">{{ __('Find your booking') }}</a>
    </td></tr>
</table>
