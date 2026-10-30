@props(['product'])
<a href="{{ route('catalog.product', $product) }}" class="group block overflow-hidden rounded-xl border border-zinc-200 bg-white transition hover:shadow-md">
    <x-product-image :product="$product" class="aspect-square w-full" />
    <div class="p-3">
        <h3 class="line-clamp-2 text-sm font-medium group-hover:text-brand-600">{{ $product->name }}</h3>
        <p class="mt-1 text-sm text-zinc-600">
            @if ($product->min_price > 0)
                de la <x-price :amount="$product->min_price" />
            @else
                Preț la cerere
            @endif
        </p>
    </div>
</a>
