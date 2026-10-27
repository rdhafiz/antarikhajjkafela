{{-- The customer's way back to the booking: the private link, and the lookup page with reference + phone. Expects $booking. --}}
<p style="margin:26px 0 0;">
    <a href="{{ $booking->publicUrl() }}" style="display:inline-block;background:#012a51;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:999px;font-weight:bold;font-size:14px;">{{ __('View your booking') }}</a>
</p>
<p style="margin:18px 0 0;font-size:13px;color:#66738a;">
    {{ __('You can also find your booking any time with your reference number and phone number:') }}
    <a href="{{ route('bookings.status') }}" style="color:#012a51;font-weight:bold;">{{ __('Find your booking') }}</a>
</p>
