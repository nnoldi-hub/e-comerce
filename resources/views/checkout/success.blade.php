<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Comandă înregistrată</title>
</head>
<body style="margin:0;padding:32px;background:#f4f4f5;font-family:system-ui,-apple-system,Segoe UI,Arial,sans-serif;color:#18181b;">
<main style="max-width:680px;margin:0 auto;background:#fff;border:1px solid #e4e4e7;border-radius:12px;padding:32px;">
    <h1 style="margin:0 0 8px;font-size:24px;">Mulțumim pentru comandă!</h1>
    <p style="margin:0 0 24px;color:#52525b;">
        Comanda <strong>{{ $order->number }}</strong> a fost înregistrată.
        Status: <strong>{{ $order->status->label() }}</strong>.
    </p>

    @if (session('payment_error'))
        <p style="margin:0 0 16px;padding:12px;border-radius:8px;background:#fef2f2;color:#991b1b;">{{ session('payment_error') }}</p>
    @endif
    @if (session('payment_notice'))
        <p style="margin:0 0 16px;padding:12px;border-radius:8px;background:#f0fdf4;color:#166534;">{{ session('payment_notice') }}</p>
    @endif

    <table style="width:100%;border-collapse:collapse;font-size:14px;">
        @foreach ($order->items as $item)
            <tr>
                <td style="padding:8px 0;border-bottom:1px solid #f4f4f5;">{{ $item->name }} ({{ $item->sku }})</td>
                <td style="padding:8px 0;border-bottom:1px solid #f4f4f5;text-align:center;">{{ $item->quantity }}</td>
                <td style="padding:8px 0;border-bottom:1px solid #f4f4f5;text-align:right;">
                    {{ number_format($item->line_total->amount() / 100, 2, ',', '.') }} {{ $order->currency }}
                </td>
            </tr>
        @endforeach
    </table>

    <p style="margin:20px 0 0;font-size:16px;">
        Total: <strong>{{ number_format($order->grand_total->amount() / 100, 2, ',', '.') }} {{ $order->currency }}</strong>
    </p>

    <p style="margin:16px 0 0;color:#52525b;">
        Metoda de plată: {{ $order->payment_method === 'cod' ? 'ramburs la livrare' : 'card bancar' }}.
        @if ($order->payment_status->value === 'pending')
            Plata este în așteptare; vei primi un email când este confirmată.
        @endif
    </p>

    @if ($order->payment_method === 'card' && $order->status === \App\Enums\OrderStatus::AWAITING_PAYMENT)
        <form method="POST"
              action="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('checkout.pay', now()->addHours(24), ['order' => $order->number]) }}"
              style="margin:20px 0 0;">
            @csrf
            <button type="submit"
                    style="padding:12px 20px;border:0;border-radius:8px;background:#2563eb;color:#fff;font-size:16px;cursor:pointer;">
                Plătește acum
            </button>
        </form>
    @endif

    <p style="margin:24px 0 0;">
        <a href="{{ route('cart.index') }}" style="color:#2563eb;">Înapoi la coș</a>
    </p>
</main>
</body>
</html>
