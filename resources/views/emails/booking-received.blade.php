@extends('emails.layout', ['eyebrow' => __('Booking received')])

@section('body')
    <p style="margin:0 0 14px;font-size:17px;font-weight:bold;">{{ __('Assalamu alaikum, :name', ['name' => $booking->customer_name]) }}</p>
    <p style="margin:0 0 20px;">{{ __('Thank you for booking with us. Your booking reference is :reference. Our team will call you shortly to confirm the details.', ['reference' => $booking->reference]) }}</p>

    @include('emails.partials.booking-summary')

    @include('emails.partials.booking-actions')
    <p style="margin:14px 0 0;font-size:13px;color:#66738a;">{{ __('We will email you whenever your booking is updated. Please make payments only through our official channels.') }}</p>
@endsection
