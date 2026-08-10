<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <div style="max-width:560px;margin:0 auto;padding:24px;">
        <div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;">
            <div style="background:#1d4ed8;padding:18px 24px;">
                <p style="margin:0;color:#ffffff;font-size:16px;font-weight:bold;">{{ $title }}</p>
                <p style="margin:4px 0 0;color:#bfdbfe;font-size:12px;">Adhi Helpdesk · Enterprise ITSM</p>
            </div>
            <div style="padding:24px;">
                <p style="margin:0 0 12px;font-size:14px;">Halo <strong>{{ $recipientName }}</strong>,</p>
                <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#374151;">{{ $body }}</p>

                @if ($ticket)
                    <table style="width:100%;border-collapse:collapse;font-size:13px;margin:8px 0 20px;">
                        <tr>
                            <td style="padding:6px 0;color:#6b7280;width:120px;">No. Tiket</td>
                            <td style="padding:6px 0;font-weight:bold;color:#2563eb;">{{ $ticket->ticket_no }}</td>
                        </tr>
                        <tr>
                            <td style="padding:6px 0;color:#6b7280;">Subjek</td>
                            <td style="padding:6px 0;color:#111827;">{{ $ticket->title }}</td>
                        </tr>
                        <tr>
                            <td style="padding:6px 0;color:#6b7280;">Prioritas</td>
                            <td style="padding:6px 0;color:#111827;">{{ $ticket->priority }}</td>
                        </tr>
                        <tr>
                            <td style="padding:6px 0;color:#6b7280;">Status</td>
                            <td style="padding:6px 0;color:#111827;">{{ $ticket->status }}</td>
                        </tr>
                        @if ($ticket->resolution_due_at)
                            <tr>
                                <td style="padding:6px 0;color:#6b7280;">Batas SLA</td>
                                <td style="padding:6px 0;color:#111827;">{{ $ticket->resolution_due_at->format('d M Y · H:i') }}</td>
                            </tr>
                        @endif
                    </table>
                @endif

                <a href="{{ $actionUrl }}" style="display:inline-block;background:#1d4ed8;color:#ffffff;text-decoration:none;font-size:14px;font-weight:bold;padding:11px 22px;border-radius:10px;">
                    {{ $ticket ? 'Buka Tiket' : 'Buka Helpdesk' }}
                </a>
            </div>
            <div style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:16px 24px;">
                <p style="margin:0;font-size:12px;color:#9ca3af;">Email ini dikirim otomatis oleh Adhi Helpdesk. Balasan ke alamat ini tidak dibaca — gunakan forum diskusi di halaman tiket untuk menanggapi.</p>
            </div>
        </div>
    </div>
</body>
</html>
