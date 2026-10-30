@props(['title', 'preheader' => ''])
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:system-ui,-apple-system,'Segoe UI',Arial,sans-serif;color:#18181b;">
@if ($preheader !== '')
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $preheader }}</div>
@endif
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;">
                <tr>
                    <td style="padding:0 4px 14px;font-size:20px;font-weight:700;">
                        <a href="{{ config('app.url') }}" style="color:#2563eb;text-decoration:none;">{{ config('app.name') }}</a>
                    </td>
                </tr>
                <tr>
                    <td style="background:#ffffff;border:1px solid #e4e4e7;border-radius:12px;padding:28px;line-height:1.5;">
                        {{ $slot }}
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 4px;font-size:12px;color:#71717a;">
                        Ai primit acest email pentru că ai plasat o comandă pe <a href="{{ config('app.url') }}" style="color:#71717a;">{{ config('app.name') }}</a>.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
