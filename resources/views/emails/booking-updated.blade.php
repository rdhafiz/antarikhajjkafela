@extends('emails.layout', ['eyebrow' => __('Booking update')])

@section('body')
    <p style="margin:0 0 14px;font-size:17px;font-weight:bold;">{{ __('Assalamu alaikum, :name', ['name' => $booking->customer_name]) }}</p>
    @if ($update->kind === 'status')
        <p style="margin:0 0 20px;">{{ __('The status of your booking :reference is now: :status.', ['reference' => $booking->reference, 'status' => App\Models\Booking::statusLabel($booking->status)]) }}</p>
    @else
        <p style="margin:0 0 20px;">{{ __('There is an update on your booking :reference.', ['reference' => $booking->reference]) }}</p>
    @endif

    @include('emails.partials.team-message', ['message' => $update->message])

    @include('emails.partials.booking-summary')

    @include('emails.partials.booking-actions')
@endsection
