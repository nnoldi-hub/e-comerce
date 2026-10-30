<x-layouts.app :description="'Descoperă produsele '.config('app.name').'.'">
    <section class="mb-10 rounded-2xl bg-brand-50 px-6 py-12 sm:px-12">
        <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">Bun venit la {{ config('app.name') }}</h1>
        <p class="mt-3 max-w-xl text-zinc-600">Produse alese cu grijă, livrare rapidă și plată sigură, cu cardul sau ramburs.</p>
    </section>

    <h2 class="mb-4 text-xl font-semibold">Produse noi</h2>

    @if ($products->isEmpty())
        <p class="text-zinc-500">Momentan nu avem produse publicate. Revino în curând.</p>
    @else
        <ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($products as $product)
                <li class="overflow-hidden rounded-xl border border-zinc-200 bg-white">
                    <x-product-image :product="$product" class="aspect-square w-full" />
                    <div class="p-3">
                        <h3 class="line-clamp-2 text-sm font-medium">{{ $product->name }}</h3>
                        <p class="mt-1 text-sm text-zinc-600">
                            @if ($product->min_price !== null)
                                de la <x-price :amount="$product->min_price" />
                            @else
                                Preț la cerere
                            @endif
                        </p>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
