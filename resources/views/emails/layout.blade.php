{{-- Shared frame for transactional emails (inline styles for mail clients). --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $title ?? setting_t('branding.site_name') }}</title></head>
<body style="margin:0;background:#f7f2e9;font-family:Arial,Helvetica,'Noto Sans Bengali',sans-serif;color:#0d1b2e;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 12px;">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:580px;background:#ffffff;border-radius:14px;overflow:hidden;">
                <tr><td style="background:#012a51;padding:22px 28px;">
                    <p style="margin:0;color:#ffffff;font-size:18px;font-weight:bold;">{{ setting_t('branding.site_name') }}</p>
                    @isset($eyebrow)<p style="margin:6px 0 0;color:#fdc802;font-size:12px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;">{{ $eyebrow }}</p>@endisset
                </td></tr>
                <tr><td style="padding:28px;font-size:15px;line-height:1.6;">
                    @yield('body')
                </td></tr>
                <tr><td style="background:#f7f2e9;padding:18px 28px;font-size:12px;color:#66738a;line-height:1.6;">
                    {{ setting_t('contact.address') }}<br>
                    {{ collect([settings('contact.primary_phone'), settings('contact.primary_email')])->filter()->implode(' · ') }}
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
