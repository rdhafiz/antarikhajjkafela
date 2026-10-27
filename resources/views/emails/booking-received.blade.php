@extends('emails.layout', [
    'eyebrow' => __('Booking received'),
    'status' => $booking->status,
    'title' => __('Assalamu alaikum, :name', ['name' => $booking->customer_name]),
    'intro' => __('Thank you for booking with us. Your booking reference is :reference. Our team will call you shortly to confirm the details.', ['reference' => $booking->reference]),
])

@section('body')
    @include('emails.partials.booking-summary')

    @include('emails.partials.booking-actions')
    <p style="margin:16px 0 0;font-size:13px;color:#66738a;">{{ __('We will email you whenever your booking is updated. Please make payments only through our official channels.') }}</p>
@endsection
