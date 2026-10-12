<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>New enquiry</title></head>
<body style="margin:0;background:#f7f2e9;font-family:Arial,Helvetica,sans-serif;color:#0d1b2e;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 12px;">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:14px;overflow:hidden;">
                <tr><td style="background:#012a51;padding:20px 28px;color:#fdc802;font-size:13px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;">
                    {{ $inquiry->source === 'lead_form' ? 'Campaign registration' : 'Website message' }}
                </td></tr>
                <tr><td style="padding:28px;">
                    <p style="margin:0 0 18px;font-size:16px;">A new enquiry was received{{ isset($inquiry->data['page']) ? ' on "'.$inquiry->data['page'].'"' : '' }}.</p>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">
                        @foreach (array_filter(['Name' => $inquiry->name, 'Phone' => $inquiry->phone, 'Email' => $inquiry->email]) as $label => $value)
                            <tr><td style="padding:8px 0;color:#66738a;width:38%;">{{ $label }}</td><td style="padding:8px 0;font-weight:bold;">{{ $value }}</td></tr>
                        @endforeach
                        @foreach (collect($inquiry->data)->except('page') as $key => $value)
                            <tr><td style="padding:8px 0;color:#66738a;vertical-align:top;">{{ str($key)->replace('_', ' ')->title() }}</td><td style="padding:8px 0;white-space:pre-line;">{{ $value }}</td></tr>
                        @endforeach
                    </table>
                    <p style="margin:26px 0 0;">
                        <a href="{{ admin_url('inquiries') }}" style="display:inline-block;background:#012a51;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:999px;font-weight:bold;font-size:14px;">Open the inquiry inbox</a>
                    </p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
