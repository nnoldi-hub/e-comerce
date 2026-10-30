@props(['order'])
<dl {{ $attributes->class(['space-y-1.5 text-sm']) }}>
    @foreach (\App\Support\OrderTotals::rows($order) as $row)
        <div @class([
            'flex justify-between gap-4',
            'border-t border-zinc-200 pt-2 text-base font-semibold' => $row['kind'] === 'total',
            'text-zinc-500' => $row['kind'] === 'info',
            'text-green-700' => $row['kind'] === 'discount',
        ])>
            <dt>{{ $row['label'] }}</dt>
            <dd>
                @if ($row['kind'] === 'free')
                    Gratuit
                @elseif ($row['kind'] === 'discount')
                    − <x-price :amount="$row['amount']" :currency="$order->currency" />
                @else
                    <x-price :amount="$row['amount']" :currency="$order->currency" />
                @endif
            </dd>
        </div>
    @endforeach
</dl>
