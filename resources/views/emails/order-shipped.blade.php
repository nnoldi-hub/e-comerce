<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <title>Comanda {{ $order->number }} a fost expediată</title>
</head>
<body style="margin:0;padding:24px;background:#f4f4f5;font-family:system-ui,-apple-system,Segoe UI,Arial,sans-serif;color:#18181b;">
<div style="max-width:560px;margin:0 auto;background:#fff;border:1px solid #e4e4e7;border-radius:12px;padding:28px;">
    <h1 style="margin:0 0 12px;font-size:22px;">Comanda ta este pe drum</h1>
    <p style="margin:0 0 16px;color:#52525b;">
        Comanda <strong>{{ $order->number }}</strong> a fost predată curierului
        <strong>{{ $shipment->carrier }}</strong>.
    </p>

    @if ($shipment->tracking_number)
        <p style="margin:0 0 8px;">AWB: <strong>{{ $shipment->tracking_number }}</strong></p>
    @endif

    @if ($shipment->tracking_url)
        <p style="margin:16px 0 0;">
            <a href="{{ $shipment->tracking_url }}" style="color:#2563eb;">Urmărește coletul</a>
        </p>
    @endif

    @if ($order->payment_method === 'cod')
        <p style="margin:16px 0 0;color:#52525b;">
            Plata se face la livrare, în valoare de
            <strong>{{ number_format($order->grand_total->amount() / 100, 2, ',', '.') }} {{ $order->currency }}</strong>.
        </p>
    @endif
</div>
</body>
</html>
