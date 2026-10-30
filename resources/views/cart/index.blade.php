<x-layouts.app title="Coș" robots="noindex">
    <h1 class="mb-6 text-2xl font-semibold">Coșul tău</h1>

    @if ($cart->items->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center">
            <p class="text-zinc-600">Coșul este gol.</p>
            <a href="{{ url('/') }}" class="mt-4 inline-block text-brand-600 hover:underline">Înapoi la magazin</a>
        </div>
    @else
        <ul class="divide-y divide-zinc-200 rounded-xl border border-zinc-200">
            @foreach ($cart->items as $item)
                @php($product = $item->variant->product)
                <li class="flex gap-4 p-4">
                    <x-product-image :product="$product" class="size-20 shrink-0 rounded-lg" />

                    <div class="min-w-0 flex-1">
                        <p class="font-medium">{{ $product->name }}</p>
                        <p class="text-xs text-zinc-500">{{ $item->variant->sku }}</p>
                        <p class="mt-1 text-sm text-zinc-600"><x-price :amount="$item->unit_price_snapshot" :currency="$cart->currency" /> / buc.</p>

                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <form method="POST" action="{{ route('cart.update', $item) }}" class="flex items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <label for="qty-{{ $item->id }}" class="sr-only">Cantitate</label>
                                <input id="qty-{{ $item->id }}" name="quantity" type="number" min="0" max="99" value="{{ $item->quantity }}"
                                       class="w-20 rounded-lg border border-zinc-300 px-2 py-1.5 text-sm">
                                <x-button variant="secondary">Actualizează</x-button>
                            </form>
                            <form method="POST" action="{{ route('cart.remove', $item) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-red-600 hover:underline">Elimină</button>
                            </form>
                        </div>
                    </div>

                    <p class="shrink-0 font-medium"><x-price :amount="$item->unit_price_snapshot * $item->quantity" :currency="$cart->currency" /></p>
                </li>
            @endforeach
        </ul>

        <div class="mt-6 flex flex-col items-end gap-3">
            <p class="text-lg">Subtotal: <strong><x-price :amount="$subtotal" :currency="$cart->currency" /></strong></p>
            <p class="text-xs text-zinc-500">Transportul și eventualele reduceri se calculează la finalizarea comenzii.</p>
            <a href="{{ route('checkout.index') }}" class="inline-flex rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-700">Finalizează comanda</a>
        </div>
    @endif
</x-layouts.app>
