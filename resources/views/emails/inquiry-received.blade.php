@extends('emails.layout', [
    'eyebrow' => $inquiry->source === 'lead_form' ? 'Campaign registration' : 'Website message',
    'title' => $inquiry->name ?: 'New enquiry',
    'intro' => 'A new enquiry was received'.(isset($inquiry->data['page']) ? ' on "'.$inquiry->data['page'].'"' : '').'.',
])

{{-- Staff email: always in English. --}}
@section('body')
    @php($phone = preg_replace('/[^0-9+]/', '', (string) $inquiry->phone))
    @if ($inquiry->phone || $inquiry->email)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#e6f6ed;border-radius:14px;margin-bottom:12px;">
            <tr><td style="padding:14px 18px;font-size:14px;line-height:1.7;">
                @if ($inquiry->phone)<a href="tel:{{ $phone }}" style="color:#047857;font-weight:bold;text-decoration:none;">{{ $inquiry->phone }}</a>@endif
                @if ($inquiry->phone && $inquiry->email) &nbsp;·&nbsp; @endif
                @if ($inquiry->email)<a href="mailto:{{ $inquiry->email }}" style="color:#047857;font-weight:bold;text-decoration:none;">{{ $inquiry->email }}</a>@endif
            </td></tr>
        </table>
    @endif

    @php($details = collect($inquiry->data)->except('page')->filter(fn ($v) => filled($v)))
    @if ($details->isNotEmpty())
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f8fc;border:1px solid #e3eaf3;border-radius:14px;">
            <tr><td style="padding:6px 18px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">
                    @foreach ($details as $key => $value)
                        <tr>
                            <td style="padding:10px 0;color:#66738a;width:38%;vertical-align:top;{{ $loop->last ? '' : 'border-bottom:1px solid #e3eaf3;' }}">{{ str($key)->replace('_', ' ')->title() }}</td>
                            <td style="padding:10px 0;font-weight:bold;white-space:pre-line;vertical-align:top;{{ $loop->last ? '' : 'border-bottom:1px solid #e3eaf3;' }}">{{ is_array($value) ? implode(', ', $value) : $value }}</td>
                        </tr>
                    @endforeach
                </table>
            </td></tr>
        </table>
    @endif

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:26px;">
        <tr><td style="background:#012a51;border-radius:999px;">
            <a href="{{ admin_url('inquiries') }}" style="display:inline-block;padding:13px 26px;color:#ffffff;text-decoration:none;font-weight:bold;font-size:14px;">Open the inquiry inbox &rarr;</a>
        </td></tr>
    </table>
@endsection
