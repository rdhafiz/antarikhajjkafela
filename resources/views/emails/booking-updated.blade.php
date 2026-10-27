@extends('emails.layout', [
    'eyebrow' => __('Booking update'),
    'status' => $booking->status,
    'title' => __('Assalamu alaikum, :name', ['name' => $booking->customer_name]),
    'intro' => $update->kind === 'status'
        ? __('The status of your booking :reference is now: :status.', ['reference' => $booking->reference, 'status' => App\Models\Booking::statusLabel($booking->status)])
        : __('There is an update on your booking :reference.', ['reference' => $booking->reference]),
])

@section('body')
    @include('emails.partials.team-message', ['message' => $update->message])

    @include('emails.partials.booking-summary')

    @include('emails.partials.booking-actions')
@endsection
