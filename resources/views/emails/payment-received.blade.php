@extends('emails.layout', ['eyebrow' => __('Payment received')])

@section('body')
    <p style="margin:0 0 14px;font-size:17px;font-weight:bold;">{{ __('Assalamu alaikum, :name', ['name' => $booking->customer_name]) }}</p>
    <p style="margin:0 0 20px;">{{ __('We received your payment of :amount through :method for booking :reference. Jazakallahu khairan.', ['amount' => App\Support\Money::format($payment->amount), 'method' => $payment->methodLabel(), 'reference' => $booking->reference]) }}</p>

    @include('emails.partials.booking-summary')

    <p style="margin:26px 0 0;">
        <a href="{{ $booking->publicUrl() }}" style="display:inline-block;background:#012a51;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:999px;font-weight:bold;font-size:14px;">{{ __('View your booking') }}</a>
    </p>
@endsection
