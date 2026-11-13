<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0; padding:24px 12px; background:#f5f5f4; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#1c1917;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%; background:#ffffff; border:1px solid #e7e5e4; border-radius:8px;">
            <tr><td style="padding:28px 28px 20px; font-size:15px; line-height:1.6;">{!! \App\Support\RichText::render($body, autolink: true) !!}</td></tr>
            <tr><td style="padding:16px 28px; border-top:1px solid #e7e5e4; font-size:12px; line-height:1.5; color:#78716c;">
                {{ $company }}
                @if ($unsubscribe) <br>Don’t want greetings and offers from us? <a href="{{ $unsubscribe }}" style="color:#78716c;">Stop these emails</a>. @endif
            </td></tr>
        </table>
    </td></tr></table>
</body>
</html>
