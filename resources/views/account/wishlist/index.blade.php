<x-account.layout title="Favorite" active="wishlist">
    @if ($products->isEmpty())
        <div class="rounded-2xl border border-dashed border-zinc-300 bg-zinc-50 px-6 py-12 text-center">
            <p class="text-lg font-medium">Nu ai produse favorite încă.</p>
            <p class="mt-1 text-sm text-zinc-500">Apasă inima de pe un produs ca să îl găsești ușor mai târziu.</p>
            <x-button-link :href="route('catalog.index')" class="mt-5">Vezi produsele</x-button-link>
        </div>
    @else
        <ul class="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-3">
            @foreach ($products as $product)
                <li><x-product-card :product="$product" /></li>
            @endforeach
        </ul>

        <div class="mt-6">{{ $products->links() }}</div>
    @endif
</x-account.layout>
