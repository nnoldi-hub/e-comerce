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
            <h1 style="font-size:22px;margin:0 0 8px;">Mulțumim pentru comandă!</h1>
            <p style="margin:0 0 24px;color:#52525b;">
                Comanda <strong>{{ $order->number }}</strong> a fost înregistrată la
                {{ $order->placed_at?->format('d.m.Y H:i') }}.
            </p>

            <h2 style="font-size:16px;margin:0 0 12px;">Produse</h2>
            <table role="presentation" width="100%" style="border-collapse:collapse;font-size:14px;">
                <thead>
                <tr style="text-align:left;color:#71717a;">
                    <th style="padding:8px 0;border-bottom:1px solid #e4e4e7;">Produs</th>
                    <th style="padding:8px 0;border-bottom:1px solid #e4e4e7;text-align:center;">Cant.</th>
                    <th style="padding:8px 0;border-bottom:1px solid #e4e4e7;text-align:right;">Total</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td style="padding:10px 0;border-bottom:1px solid #f4f4f5;">
                            {{ $item->name }}<br>
                            <span style="color:#a1a1aa;font-size:12px;">{{ $item->sku }}</span>
                        </td>
                        <td style="padding:10px 0;border-bottom:1px solid #f4f4f5;text-align:center;">{{ $item->quantity }}</td>
                        <td style="padding:10px 0;border-bottom:1px solid #f4f4f5;text-align:right;">
                            {{ number_format($item->line_total->amount() / 100, 2, ',', '.') }} {{ $order->currency }}
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <table role="presentation" width="100%" style="margin-top:16px;font-size:14px;">
                <tr>
                    <td style="padding:4px 0;color:#71717a;">Subtotal</td>
                    <td style="padding:4px 0;text-align:right;">{{ number_format($order->subtotal->amount() / 100, 2, ',', '.') }} {{ $order->currency }}</td>
                </tr>
                @if ($order->discount_total->amount() > 0)
                    <tr>
                        <td style="padding:4px 0;color:#71717a;">Reducere</td>
                        <td style="padding:4px 0;text-align:right;">-{{ number_format($order->discount_total->amount() / 100, 2, ',', '.') }} {{ $order->currency }}</td>
                    </tr>
                @endif
                <tr>
                    <td style="padding:4px 0;color:#71717a;">Livrare</td>
                    <td style="padding:4px 0;text-align:right;">{{ number_format($order->shipping_total->amount() / 100, 2, ',', '.') }} {{ $order->currency }}</td>
                </tr>
                <tr>
                    <td style="padding:10px 0;border-top:1px solid #e4e4e7;font-weight:bold;">Total</td>
                    <td style="padding:10px 0;border-top:1px solid #e4e4e7;text-align:right;font-weight:bold;">
                        {{ number_format($order->grand_total->amount() / 100, 2, ',', '.') }} {{ $order->currency }}
                    </td>
                </tr>
            </table>

            <h2 style="font-size:16px;margin:24px 0 8px;">Livrare</h2>
            <p style="margin:0;color:#52525b;line-height:1.6;">
                {{ $order->shipping_address['first_name'] ?? '' }} {{ $order->shipping_address['last_name'] ?? '' }}<br>
                {{ $order->shipping_address['address_line1'] ?? '' }}<br>
                @if (! empty($order->shipping_address['address_line2']))
                    {{ $order->shipping_address['address_line2'] }}<br>
                @endif
                {{ $order->shipping_address['city'] ?? '' }}, {{ $order->shipping_address['postal_code'] ?? '' }}<br>
                {{ $order->shipping_address['country'] ?? '' }}<br>
                Tel: {{ $order->shipping_address['phone'] ?? '' }}
            </p>

            <p style="margin:24px 0 0;color:#52525b;">
                Metoda de plată: <strong>{{ $order->payment_method === 'cod' ? 'ramburs la livrare' : 'card bancar' }}</strong>.
            </p>
        </td>
    </tr>
</table>
</body>
</html>
