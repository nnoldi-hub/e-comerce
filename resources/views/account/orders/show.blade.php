@php
    $address = function (?array $a): string {
        if (! $a) {
            return '—';
        }
        $lines = array_filter([
            trim(($a['first_name'] ?? '').' '.($a['last_name'] ?? '')),
            $a['company_name'] ?? null,
            ! empty($a['cui']) ? 'CUI: '.$a['cui'] : null,
            $a['address_line1'] ?? null,
            $a['address_line2'] ?? null,
            trim(($a['postal_code'] ?? '').' '.($a['city'] ?? '').(! empty($a['county']) ? ', '.$a['county'] : '')),
            $a['phone'] ?? null,
        ]);
        return implode("\n", $lines);
    };
@endphp
<x-account.layout :title="'Comanda '.$order->number" active="orders">
    <p class="mb-6 flex flex-wrap items-center gap-3 text-sm text-zinc-600">
        <x-order-status :status="$order->status" />
        <span>Plasată pe {{ $order->placed_at?->format('d.m.Y H:i') }}</span>
        <span>· {{ $order->payment_method === 'cod' ? 'Ramburs la livrare' : 'Card bancar' }}</span>
    </p>

    @if ($payable)
        <form method="POST"
              action="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('checkout.pay', now()->addHours(24), ['order' => $order->number]) }}"
              class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4">
            @csrf
            <p class="mb-3 text-sm text-amber-900">Comanda așteaptă plata. Poți reveni oricând să o achiți.</p>
            <x-button>Plătește acum</x-button>
        </form>
    @endif

    @if ($shipment)
        <section class="mb-6 rounded-xl border border-zinc-200 p-4 text-sm" aria-labelledby="h-ship">
            <h2 id="h-ship" class="mb-1 text-lg font-semibold">Livrare</h2>
            <p>Curier: <strong>{{ $shipment->carrier }}</strong></p>
            @if ($shipment->tracking_number)
                <p>AWB: <strong>{{ $shipment->tracking_number }}</strong></p>
            @endif
            @if ($shipment->tracking_url)
                <p><a href="{{ $shipment->tracking_url }}" target="_blank" rel="noopener noreferrer" class="text-brand-600 hover:underline">Urmărește coletul</a></p>
            @endif
            @if ($shipment->delivered_at)
                <p class="text-zinc-500">Livrat pe {{ $shipment->delivered_at->format('d.m.Y') }}</p>
            @endif
        </section>
    @endif

    <section aria-labelledby="h-items">
        <h2 id="h-items" class="sr-only">Produse</h2>
        <table class="w-full text-sm">
            <caption class="sr-only">Produsele comenzii</caption>
            <tbody class="divide-y divide-zinc-100 border-y border-zinc-100">
                @foreach ($order->items as $item)
                    <tr>
                        <td class="py-3">{{ $item->name }} <span class="text-zinc-500">({{ $item->sku }})</span></td>
                        <td class="py-3 text-center">× {{ $item->quantity }}</td>
                        <td class="py-3 text-right"><x-price :amount="$item->line_total" :currency="$order->currency" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <dl class="mt-4 ml-auto max-w-xs space-y-1 text-sm">
            <div class="flex justify-between"><dt>Subtotal</dt><dd><x-price :amount="$order->subtotal" :currency="$order->currency" /></dd></div>
            @if ($order->discount_total->amount() > 0)
                <div class="flex justify-between"><dt>Reducere</dt><dd>− <x-price :amount="$order->discount_total" :currency="$order->currency" /></dd></div>
            @endif
            <div class="flex justify-between"><dt>Transport</dt><dd><x-price :amount="$order->shipping_total" :currency="$order->currency" /></dd></div>
            <div class="flex justify-between border-t border-zinc-200 pt-2 text-base font-semibold"><dt>Total</dt><dd><x-price :amount="$order->grand_total" :currency="$order->currency" /></dd></div>
        </dl>
    </section>

    <div class="mt-8 grid gap-6 sm:grid-cols-2">
        <section aria-labelledby="h-sa">
            <h2 id="h-sa" class="mb-1 text-lg font-semibold">Adresă de livrare</h2>
            <p class="whitespace-pre-line text-sm text-zinc-700">{{ $address($order->shipping_address) }}</p>
        </section>
        <section aria-labelledby="h-ba">
            <h2 id="h-ba" class="mb-1 text-lg font-semibold">Date de facturare</h2>
            <p class="whitespace-pre-line text-sm text-zinc-700">{{ $address($order->billing_address) }}</p>
        </section>
    </div>

    <section class="mt-8" aria-labelledby="h-hist">
        <h2 id="h-hist" class="mb-2 text-lg font-semibold">Istoric</h2>
        <ol class="space-y-2 border-l border-zinc-200 pl-4 text-sm">
            @foreach ($order->statusHistories as $entry)
                <li>
                    <span class="font-medium">{{ $entry->to_status->label() }}</span>
                    <span class="text-zinc-500">· {{ $entry->created_at?->format('d.m.Y H:i') }}</span>
                </li>
            @endforeach
        </ol>
    </section>

    <p class="mt-8"><a href="{{ route('account.orders.index') }}" class="text-brand-600 hover:underline">← Înapoi la comenzi</a></p>
</x-account.layout>
