<x-mail.layout :title="'Comanda '.($order->number).' a fost înregistrată'" :preheader="'Comanda '.($order->number).' a fost înregistrată.'">
    <h1 style="margin:0 0 8px;font-size:22px;">Mulțumim pentru comandă!</h1>
    <p style="margin:0 0 20px;color:#52525b;">
        Comanda <strong>{{ $order->number }}</strong> a fost înregistrată. Te anunțăm prin email la fiecare pas.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">
        @foreach ($order->items as $item)
            <tr>
                <td style="padding:8px 0;border-bottom:1px solid #f4f4f5;">
                    {{ $item->name }}<br><span style="color:#71717a;font-size:12px;">{{ $item->sku }}</span>
                </td>
                <td align="center" style="padding:8px 8px;border-bottom:1px solid #f4f4f5;white-space:nowrap;">× {{ $item->quantity }}</td>
                <td align="right" style="padding:8px 0;border-bottom:1px solid #f4f4f5;white-space:nowrap;">
                    {{ number_format((is_object($item->line_total) ? $item->line_total->amount() : (int) $item->line_total) / 100, 2, ',', '.') }} {{ $order->currency }}
                </td>
            </tr>
        @endforeach
    </table>

    @include('mail.partials.totals', ['order' => $order])

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:24px;font-size:14px;">
        <tr>
            <td valign="top" width="50%" style="padding-right:12px;">
                <strong>Livrare la</strong><br>
                <span style="color:#52525b;">
                    @foreach (\App\Support\AddressFormatter::lines($order->shipping_address) as $line)
                        {{ $line }}@unless ($loop->last)<br>@endunless
                    @endforeach
                </span>
            </td>
            <td valign="top" width="50%">
                <strong>Plată</strong><br>
                <span style="color:#52525b;">
                    @if ($order->payment_method === 'cod')
                        Ramburs la livrare: plătești curierului
                        {{ number_format($order->grand_total->amount() / 100, 2, ',', '.') }} {{ $order->currency }}.
                    @else
                        Card bancar.
                    @endif
                </span>
            </td>
        </tr>
    </table>

    <p style="margin:24px 0 0;">
        <a href="{{ config('app.url') }}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:600;">Vezi magazinul</a>
    </p>
</x-mail.layout>
