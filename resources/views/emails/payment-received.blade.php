@extends('emails.layout', ['eyebrow' => __('Payment received')])

@section('body')
    <p style="margin:0 0 14px;font-size:17px;font-weight:bold;">{{ __('Assalamu alaikum, :name', ['name' => $booking->customer_name]) }}</p>
    <p style="margin:0 0 20px;">{{ __('We received your payment of :amount through :method for booking :reference. Jazakallahu khairan.', ['amount' => App\Support\Money::format($payment->amount), 'method' => $payment->methodLabel(), 'reference' => $booking->reference]) }}</p>

    @include('emails.partials.team-message', ['message' => $update?->message])

    @include('emails.partials.booking-summary')

    @include('emails.partials.booking-actions')
@endsection
