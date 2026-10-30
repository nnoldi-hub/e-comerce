<x-layouts.app :description="'Descoperă produsele '.config('app.name').'.'">
    <x-slot:head>
        <script type="application/ld+json">{!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('app.name'),
            'url' => url('/'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => route('catalog.index').'?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    </x-slot:head>

    <section class="mb-10 rounded-2xl bg-brand-50 px-6 py-12 sm:px-12">
        <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">Bun venit la {{ config('app.name') }}</h1>
        <p class="mt-3 max-w-xl text-zinc-600">Produse alese cu grijă, livrare rapidă și plată sigură, cu cardul sau ramburs.</p>
    </section>

    <div class="mb-4 flex items-baseline justify-between">
        <h2 class="text-xl font-semibold">Produse noi</h2>
        <a href="{{ route('catalog.index') }}" class="text-sm text-brand-600 hover:underline">Vezi toate produsele</a>
    </div>

    @if ($products->isEmpty())
        <p class="text-zinc-500">Momentan nu avem produse publicate. Revino în curând.</p>
    @else
        <ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($products as $product)
                <li><x-product-card :product="$product" /></li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
