<x-layouts.app title="Coș" robots="noindex">
    <h1 class="mb-6 text-2xl font-bold tracking-tight sm:text-3xl">Coșul tău</h1>

    @if ($cart->items->isEmpty())
        <div class="rounded-3xl border border-dashed border-zinc-300 bg-zinc-50 px-6 py-14 text-center">
            <span class="mx-auto flex size-16 items-center justify-center rounded-full bg-white text-zinc-400 shadow-sm">
                <x-ui-icon name="cart" class="size-8" />
            </span>
            <p class="mt-4 text-lg font-medium">Coșul este gol.</p>
            <p class="mt-1 text-sm text-zinc-500">Adaugă produse ca să poți plasa o comandă.</p>
            <x-button-link :href="route('catalog.index')" class="mt-6">Înapoi la magazin</x-button-link>
        </div>
    @else
        <div class="grid items-start gap-8 lg:grid-cols-3">
            <ul class="divide-y divide-zinc-200 overflow-hidden rounded-2xl border border-zinc-200 bg-white lg:col-span-2">
                @foreach ($cart->items as $item)
                    @php($product = $item->variant->product)
                    <li class="flex gap-3 p-4 sm:gap-5 sm:p-5">
                        <a href="{{ route('catalog.product', $product) }}" class="shrink-0">
                            <x-product-image :product="$product" class="size-20 rounded-xl sm:size-24" />
                        </a>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <a href="{{ route('catalog.product', $product) }}" class="line-clamp-2 font-medium hover:text-brand-700">{{ $product->name }}</a>
                                    <p class="mt-0.5 text-xs text-zinc-500">{{ $item->variant->sku }}</p>
                                    <p class="mt-1 text-sm text-zinc-600"><x-price :amount="$item->unit_price_snapshot" :currency="$cart->currency" /> / buc.</p>
                                </div>
                                <p class="shrink-0 font-semibold"><x-price :amount="$item->unit_price_snapshot * $item->quantity" :currency="$cart->currency" /></p>
                            </div>

                            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2">
                                <form method="POST" action="{{ route('cart.update', $item) }}" class="flex items-center gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <label for="qty-{{ $item->id }}" class="sr-only">Cantitate</label>
                                    <input id="qty-{{ $item->id }}" name="quantity" type="number" min="0" max="99" value="{{ $item->quantity }}"
                                           class="w-20 rounded-lg border border-zinc-300 bg-white px-2 py-1.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                                    <x-button variant="secondary">Actualizează</x-button>
                                </form>
                                <form method="POST" action="{{ route('cart.remove', $item) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm text-red-600 hover:underline">Elimină</button>
                                </form>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            <aside class="rounded-2xl border border-zinc-200 bg-zinc-50 p-5 lg:sticky lg:top-36">
                <h2 class="text-lg font-semibold">Sumar comandă</h2>
                <p class="mt-4 flex items-baseline justify-between text-base">
                    <span>Subtotal</span>
                    <strong class="text-xl"><x-price :amount="$subtotal" :currency="$cart->currency" /></strong>
                </p>
                <p class="mt-2 text-xs text-zinc-500">Transportul și eventualele reduceri se calculează la finalizarea comenzii.</p>

                <x-button-link :href="route('checkout.index')" variant="cta" class="mt-5 w-full py-3 text-base">Finalizează comanda</x-button-link>
                <a href="{{ route('catalog.index') }}" class="mt-3 block text-center text-sm text-brand-600 hover:underline">Continuă cumpărăturile</a>
            </aside>
        </div>
    @endif
</x-layouts.app>
