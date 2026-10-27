{{-- A message from the team to the customer, set apart from the details. Expects $message (may be empty). --}}
@if (filled($message ?? null))
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">
        <tr><td style="background:#f1edfd;border-left:4px solid #7c3aed;border-radius:10px;padding:14px 16px;">
            <p style="margin:0 0 4px;font-size:11px;font-weight:bold;color:#5b21b6;letter-spacing:0.6px;text-transform:uppercase;">{{ __('Message from our team') }}</p>
            <p style="margin:0;font-size:15px;line-height:1.6;color:#0d1b2e;white-space:pre-line;">{{ $message }}</p>
        </td></tr>
    </table>
@endif
