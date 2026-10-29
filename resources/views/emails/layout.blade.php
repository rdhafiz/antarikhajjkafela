{{--
    Shared frame for transactional emails, in the website's look: a navy header with the light logo
    and a gold rule, a white card, soft-shaded panels and gold buttons. Inline styles and tables only,
    so it holds up in Gmail, Outlook and phone mail apps.
    Variables: $eyebrow (label in the header), $title (heading), $intro (line under it), $status (booking status for the pill).
--}}
@php
    // Hajj and Umrah bookings are written as Antarik Hajj Kafela.
    $brand = app(App\Support\Branding::class)->forBooking($booking ?? null);
    $logo = $brand->lightLogo()['src'] ?? null;
    $siteName = $brand->siteName();
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $title ?? $siteName }}</title>
</head>
<body style="margin:0;padding:0;background:#eef3f9;font-family:Arial,Helvetica,'Noto Sans Bengali',sans-serif;color:#0d1b2e;-webkit-text-size-adjust:100%;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef3f9;padding:28px 12px;">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #dfe7f1;">
                {{-- Header: light logo on navy, the email's label on the right --}}
                <tr><td style="background:#012a51;padding:22px 28px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="vertical-align:middle;">
                                @if ($logo)
                                    <img src="{{ $logo }}" alt="{{ $siteName }}" height="44" style="display:block;height:44px;width:auto;border:0;">
                                @else
                                    <span style="color:#ffffff;font-size:18px;font-weight:bold;">{{ $siteName }}</span>
                                @endif
                            </td>
                            @isset($eyebrow)
                                <td align="right" style="vertical-align:middle;">
                                    <span style="display:inline-block;background:#fdc802;color:#012a51;font-size:11px;font-weight:bold;letter-spacing:0.8px;text-transform:uppercase;padding:6px 12px;border-radius:999px;white-space:nowrap;">{{ $eyebrow }}</span>
                                </td>
                            @endisset
                        </tr>
                    </table>
                </td></tr>
                <tr><td style="height:4px;line-height:4px;font-size:0;background:#fdc802;">&nbsp;</td></tr>

                {{-- Heading --}}
                @if (isset($title) || isset($status))
                    <tr><td style="padding:28px 28px 0;">
                        @isset($status)
                            @include('emails.partials.status-pill', ['status' => $status])
                        @endisset
                        @isset($title)
                            <h1 style="margin:{{ isset($status) ? '14px' : '0' }} 0 0;font-size:22px;line-height:1.3;color:#012a51;">{{ $title }}</h1>
                        @endisset
                        @isset($intro)
                            <p style="margin:10px 0 0;font-size:15px;line-height:1.6;color:#3a485c;">{{ $intro }}</p>
                        @endisset
                    </td></tr>
                @endif

                <tr><td style="padding:24px 28px 30px;font-size:15px;line-height:1.6;">
                    @yield('body')
                </td></tr>

                {{-- Footer --}}
                <tr><td style="background:#f5f8fc;border-top:1px solid #e3eaf3;padding:20px 28px;font-size:12px;color:#66738a;line-height:1.7;">
                    <strong style="color:#012a51;">{{ $siteName }}</strong><br>
                    {{ setting_t('contact.address') }}<br>
                    {{ collect([settings('contact.primary_phone'), settings('contact.primary_email')])->filter()->implode(' · ') }}
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
