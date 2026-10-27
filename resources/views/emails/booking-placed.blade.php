@extends('emails.layout', [
    'eyebrow' => 'New booking',
    'status' => $booking->status,
    'title' => $booking->reference.' · '.$booking->customer_name,
    'intro' => 'A new booking was placed on the website. Call the customer to confirm it.',
])

{{-- Staff email: always in English. --}}
@section('body')
    @include('emails.partials.booking-summary', ['locale' => 'en', 'amounts' => true])

    {{-- The customer, with one-tap call and email links --}}
    @php
        $phone = preg_replace('/[^0-9+]/', '', (string) $booking->customer_phone);
        $documents = $booking->documents()->count();
    @endphp
    <p style="margin:24px 0 8px;font-size:11px;font-weight:bold;letter-spacing:0.6px;text-transform:uppercase;color:#66738a;">Customer</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#e6f6ed;border-radius:14px;">
        <tr><td style="padding:14px 18px;font-size:14px;line-height:1.7;">
            <p style="margin:0;font-size:16px;font-weight:bold;color:#0d1b2e;">{{ $booking->customer_name }}</p>
            <p style="margin:4px 0 0;"><a href="tel:{{ $phone }}" style="color:#047857;font-weight:bold;text-decoration:none;">{{ $booking->customer_phone }}</a>
                @if ($booking->customer_email) &nbsp;·&nbsp; <a href="mailto:{{ $booking->customer_email }}" style="color:#047857;font-weight:bold;text-decoration:none;">{{ $booking->customer_email }}</a>@endif
            </p>
            @if ($booking->customer_address)<p style="margin:4px 0 0;color:#3a485c;">{{ $booking->customer_address }}</p>@endif
            @if ($documents)<p style="margin:4px 0 0;color:#3a485c;">{{ $documents }} {{ $documents === 1 ? 'document' : 'documents' }} uploaded</p>@endif
        </td></tr>
    </table>
    @if ($booking->customer_note)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:12px;">
            <tr><td style="background:#fff1e3;border-left:4px solid #f59e0b;border-radius:10px;padding:12px 16px;">
                <p style="margin:0 0 4px;font-size:11px;font-weight:bold;color:#9a3412;letter-spacing:0.6px;text-transform:uppercase;">Message from the customer</p>
                <p style="margin:0;font-size:14px;line-height:1.6;white-space:pre-line;">{{ $booking->customer_note }}</p>
            </td></tr>
        </table>
    @endif

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:26px;">
        <tr><td style="background:#012a51;border-radius:999px;">
            <a href="{{ admin_url('bookings/'.$booking->id) }}" style="display:inline-block;padding:13px 26px;color:#ffffff;text-decoration:none;font-weight:bold;font-size:14px;">Open the booking &rarr;</a>
        </td></tr>
    </table>
@endsection
