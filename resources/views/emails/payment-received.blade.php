@extends('emails.layout', [
    'eyebrow' => __('Payment received'),
    'status' => $booking->status,
    'title' => __('Assalamu alaikum, :name', ['name' => $booking->customer_name]),
    'intro' => __('We received your payment of :amount through :method for booking :reference. Jazakallahu khairan.', ['amount' => App\Support\Money::format($payment->amount), 'method' => $payment->customerMethodLabel(), 'reference' => $booking->reference]),
])

@section('body')
    @include('emails.partials.team-message', ['message' => $update?->message])

    @include('emails.partials.booking-summary')

    @include('emails.partials.booking-actions')
@endsection
