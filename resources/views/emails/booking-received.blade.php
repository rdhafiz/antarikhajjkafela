@extends('emails.layout', ['eyebrow' => __('Booking received')])

@section('body')
    <p style="margin:0 0 14px;font-size:17px;font-weight:bold;">{{ __('Assalamu alaikum, :name', ['name' => $booking->customer_name]) }}</p>
    <p style="margin:0 0 20px;">{{ __('Thank you for booking with us. Your booking reference is :reference. Our team will call you shortly to confirm the details.', ['reference' => $booking->reference]) }}</p>

    @include('emails.partials.booking-summary')

    <p style="margin:26px 0 0;">
        <a href="{{ $booking->publicUrl() }}" style="display:inline-block;background:#012a51;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:999px;font-weight:bold;font-size:14px;">{{ __('View your booking') }}</a>
    </p>
    <p style="margin:18px 0 0;font-size:13px;color:#66738a;">{{ __('Keep this email: the link above shows your booking status and payments at any time. Please make payments only through our official channels.') }}</p>
@endsection
