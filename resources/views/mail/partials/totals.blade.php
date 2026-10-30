<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:12px;font-size:14px;">
    @foreach (\App\Support\OrderTotals::rows($order) as $row)
        @php
            $money = number_format($row['amount'] / 100, 2, ',', '.').' '.$order->currency;
            $style = match ($row['kind']) {
                'total' => 'border-top:1px solid #e4e4e7;padding:10px 0 0;font-size:16px;font-weight:700;',
                'info' => 'padding:3px 0;color:#71717a;',
                'discount' => 'padding:3px 0;color:#15803d;',
                default => 'padding:3px 0;',
            };
        @endphp
        <tr>
            <td style="{{ $style }}">{{ $row['label'] }}</td>
            <td align="right" style="{{ $style }}white-space:nowrap;">
                @if ($row['kind'] === 'free') Gratuit @elseif ($row['kind'] === 'discount') − {{ $money }} @else {{ $money }} @endif
            </td>
        </tr>
    @endforeach
</table>
