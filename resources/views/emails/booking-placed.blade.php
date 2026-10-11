@extends('emails.layout', ['eyebrow' => 'New booking'])

{{-- Staff email: always in English. --}}
@section('body')
    <p style="margin:0 0 18px;font-size:16px;">A new booking was placed on the website.</p>

    @include('emails.partials.booking-summary', ['locale' => 'en'])

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;margin-top:18px;">
        @foreach (array_filter(['Name' => $booking->customer_name, 'Phone' => $booking->customer_phone, 'Email' => $booking->customer_email, 'Address' => $booking->customer_address, 'Message' => $booking->customer_note, 'Documents' => $booking->documents()->count() ?: null]) as $label => $value)
            <tr><td style="padding:7px 0;color:#66738a;width:40%;vertical-align:top;">{{ $label }}</td><td style="padding:7px 0;white-space:pre-line;">{{ $value }}</td></tr>
        @endforeach
    </table>

    <p style="margin:26px 0 0;">
        <a href="{{ url('/admin/bookings/'.$booking->id) }}" style="display:inline-block;background:#012a51;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:999px;font-weight:bold;font-size:14px;">Open the booking</a>
    </p>
@endsection
