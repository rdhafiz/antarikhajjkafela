{{-- A message from the team to the customer, set apart from the details. Expects $message (may be empty). --}}
@if (filled($message ?? null))
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 22px;">
        <tr><td style="background:#eaf3ff;border-left:4px solid #012a51;border-radius:8px;padding:14px 16px;">
            <p style="margin:0 0 4px;font-size:12px;font-weight:bold;color:#012a51;letter-spacing:0.5px;text-transform:uppercase;">{{ __('Message from our team') }}</p>
            <p style="margin:0;white-space:pre-line;">{{ $message }}</p>
        </td></tr>
    </table>
@endif
