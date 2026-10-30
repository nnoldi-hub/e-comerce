<x-layouts.app title="Comandă înregistrată" robots="noindex">
    <div class="mx-auto max-w-2xl rounded-xl border border-zinc-200 bg-white p-6 sm:p-8">
        <h1 class="text-2xl font-semibold">Mulțumim pentru comandă!</h1>
        <p class="mt-2 text-zinc-600">
            Comanda <strong>{{ $order->number }}</strong> a fost înregistrată.
            Status: <strong>{{ $order->status->label() }}</strong>.
        </p>

        <table class="mt-6 w-full text-sm">
            <caption class="sr-only">Produsele comandate</caption>
            <tbody>
                @foreach ($order->items as $item)
                    <tr class="border-b border-zinc-100">
                        <td class="py-2">{{ $item->name }} <span class="text-zinc-500">({{ $item->sku }})</span></td>
                        <td class="py-2 text-center">{{ $item->quantity }}</td>
                        <td class="py-2 text-right"><x-price :amount="$item->line_total" :currency="$order->currency" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p class="mt-5 text-base">Total: <strong><x-price :amount="$order->grand_total" :currency="$order->currency" /></strong></p>

        <p class="mt-4 text-sm text-zinc-600">
            Metoda de plată: {{ $order->payment_method === 'cod' ? 'ramburs la livrare' : 'card bancar' }}.
            @if ($order->payment_status->value === 'pending')
                Plata este în așteptare; vei primi un email când este confirmată.
            @endif
        </p>

        @if ($order->payment_method === 'card' && $order->status === \App\Enums\OrderStatus::AWAITING_PAYMENT)
            <form method="POST"
                  action="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('checkout.pay', now()->addHours(24), ['order' => $order->number]) }}"
                  class="mt-5">
                @csrf
                <x-button class="px-5 py-3">Plătește acum</x-button>
            </form>
        @endif

        <p class="mt-6"><a href="{{ route('cart.index') }}" class="text-brand-600 hover:underline">Înapoi la coș</a></p>
    </div>
</x-layouts.app>
