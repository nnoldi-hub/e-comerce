<x-mail.layout :title="'Comanda '.($order->number).' a fost expediată'" :preheader="'Comanda '.($order->number).' este pe drum.'">
    <h1 style="margin:0 0 8px;font-size:22px;">Comanda ta este pe drum</h1>
    <p style="margin:0 0 16px;color:#52525b;">
        Comanda <strong>{{ $order->number }}</strong> a fost predată curierului
        <strong>{{ $shipment->carrier }}</strong>.
    </p>

    @if ($shipment->tracking_number)
        <p style="margin:0 0 8px;">AWB: <strong>{{ $shipment->tracking_number }}</strong></p>
    @endif

    @if ($shipment->tracking_url)
        <p style="margin:16px 0 0;">
            <a href="{{ $shipment->tracking_url }}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:600;">Urmărește coletul</a>
        </p>
    @endif

    @if ($order->payment_method === 'cod')
        <p style="margin:16px 0 0;color:#52525b;">
            Plata se face la livrare, în valoare de
            <strong>{{ number_format($order->grand_total->amount() / 100, 2, ',', '.') }} {{ $order->currency }}</strong>.
        </p>
    @endif
</x-mail.layout>
