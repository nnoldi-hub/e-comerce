<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <title>Comanda {{ $order->number }}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#18181b;">
<table role="presentation" width="100%" style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:12px;padding:32px;border:1px solid #e4e4e7;">
    <tr>
        <td>
            <h1 style="font-size:22px;margin:0 0 8px;">Statusul comenzii s-a schimbat</h1>
            <p style="margin:0 0 24px;color:#52525b;">
                Comanda <strong>{{ $order->number }}</strong> are acum statusul
                <strong>{{ $to->label() }}</strong>.
            </p>

            <p style="margin:0 0 24px;color:#52525b;">
                Status anterior: {{ $from->label() }}
            </p>

            @if ($comment)
                <div style="padding:16px;background:#f4f4f5;border-radius:8px;color:#3f3f46;">
                    {{ $comment }}
                </div>
            @endif

            <p style="margin:24px 0 0;color:#52525b;">
                Total comandă:
                <strong>{{ number_format($order->grand_total->amount() / 100, 2, ',', '.') }} {{ $order->currency }}</strong>
            </p>
        </td>
    </tr>
</table>
</body>
</html>
