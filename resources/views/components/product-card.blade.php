@props(['product'])
@php
    $card = \App\Support\ProductCardData::from($product);
    $productUrl = route('catalog.product', $product);
@endphp
{{--
    <article> cu link „întins” pe titlu (after:inset-0): tot cardul e clicabil, iar formularul
    „Adaugă în coș” stă deasupra (z-10). Un <form> în interiorul unui <a> ar fi HTML invalid.
--}}
<article class="group relative flex h-full flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm transition duration-300 hover:-translate-y-1 hover:border-zinc-300 hover:shadow-xl motion-reduce:transition-none motion-reduce:hover:translate-y-0">
    <div class="relative overflow-hidden bg-white">
        <x-product-image :product="$product" fit="contain"
                         class="aspect-square w-full p-3 transition duration-300 group-hover:scale-105 motion-reduce:transition-none motion-reduce:group-hover:scale-100" />

        @if ($product->exists)
            <x-heart-button :product="$product" :wished="app(\App\Support\Wishlist::class)->has($product->id)" class="absolute right-2 top-2" />
        @endif

        @if ($card->discountPercent > 0 || $card->isNew || $card->lowStock || $card->soldOut || $card->bestseller)
            <ul class="pointer-events-none absolute left-2 top-2 flex flex-col items-start gap-1 text-xs font-bold" aria-label="Etichete produs">
                @if ($card->discountPercent > 0)
                    <li class="rounded-md bg-red-600 px-2 py-0.5 text-white">−{{ $card->discountPercent }}%</li>
                @endif
                @if ($card->bestseller)
                    <li class="rounded-md bg-accent-600 px-2 py-0.5 text-white">Bestseller</li>
                @endif
                @if ($card->isNew)
                    <li class="rounded-md bg-brand-600 px-2 py-0.5 text-white">Nou</li>
                @endif
                @if ($card->soldOut)
                    <li class="rounded-md bg-zinc-800 px-2 py-0.5 text-white">Stoc epuizat</li>
                @elseif ($card->lowStock)
                    <li class="rounded-md bg-amber-100 px-2 py-0.5 text-amber-900">Stoc redus</li>
                @endif
            </ul>
        @endif
    </div>

    <div class="flex flex-1 flex-col border-t border-zinc-100 p-3 sm:p-4">
        <h3 class="line-clamp-2 min-h-10 text-sm font-medium leading-snug">
            <a href="{{ $productUrl }}" class="after:absolute after:inset-0 hover:text-brand-700">{{ $product->name }}</a>
        </h3>

        @if ($card->reviews > 0)
            <x-rating-stars :rating="$card->rating" :count="$card->reviews" class="mt-1.5" />
        @endif

        <p class="mt-2 flex flex-wrap items-baseline gap-x-2 text-sm">
            @if ($product->min_price > 0)
                @if ($card->variant === null)<span class="text-zinc-500">de la</span>@endif
                <x-price :amount="$product->min_price" class="text-base font-bold text-zinc-900" />
                @if ($card->compareAt !== null)
                    <x-price :amount="$card->compareAt" class="text-xs text-zinc-400 line-through" />
                @endif
            @else
                <span class="text-zinc-500">Preț la cerere</span>
            @endif
        </p>

        <div class="relative z-10 mt-auto pt-3">
            @if ($card->variant !== null && $card->purchasable)
                <form method="POST" action="{{ route('cart.add', $card->variant) }}">
                    @csrf
                    <x-button variant="cta" class="w-full">
                        <x-ui-icon name="cart" class="size-4" /> Adaugă în coș
                    </x-button>
                </form>
            @elseif ($card->soldOut)
                <x-button type="button" variant="secondary" class="w-full" disabled>Stoc epuizat</x-button>
            @else
                <x-button-link :href="$productUrl" variant="secondary" class="w-full">
                    {{ $card->variant === null && $product->min_price > 0 ? 'Alege opțiunea' : 'Vezi detalii' }}
                </x-button-link>
            @endif
        </div>
    </div>
</article>
