<x-mail.layout :title="'Comanda '.($order->number).': '.($to->label()).''" :preheader="'Comanda '.($order->number).' are acum statusul: '.($to->label()).'.'">
    <h1 style="margin:0 0 8px;font-size:22px;">Comanda ta: {{ $to->label() }}</h1>
    <p style="margin:0 0 16px;color:#52525b;">
        Statusul comenzii <strong>{{ $order->number }}</strong> s-a schimbat din
        <strong>{{ $from->label() }}</strong> în <strong>{{ $to->label() }}</strong>.
    </p>

    @if (filled($comment))
        <p style="margin:0 0 16px;padding:12px 14px;background:#f4f4f5;border-radius:8px;color:#3f3f46;">{{ $comment }}</p>
    @endif

    <p style="margin:0;font-size:14px;color:#52525b;">
        Total comandă:
        <strong>{{ number_format($order->grand_total->amount() / 100, 2, ',', '.') }} {{ $order->currency }}</strong>
    </p>
</x-mail.layout>
