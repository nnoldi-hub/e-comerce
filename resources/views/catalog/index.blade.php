@php
    $heading = $category?->name ?? ($filters['q'] !== '' ? 'Rezultate pentru „'.$filters['q'].'”' : 'Toate produsele');
    $currentPath = $category?->path;
    $noindex = $filters['q'] !== '' || $filters['brands'] !== [] || $filters['min'] !== null || $filters['max'] !== null || $filters['in_stock'] || $filters['sort'] !== 'newest' || $products->currentPage() > 1;
    $resetUrl = $category ? route('catalog.category', ['path' => $category->path]) : route('catalog.index');
@endphp
<x-layouts.app :title="$category?->meta_title ?: $heading"
               :description="$category?->meta_description"
               :robots="$noindex ? 'noindex,follow' : null">
    @if ($breadcrumbs->isNotEmpty())
        <nav aria-label="Breadcrumb" class="mb-4 text-sm text-zinc-500">
            <a href="{{ route('catalog.index') }}" class="hover:underline">Produse</a>
            @foreach ($breadcrumbs as $crumb)
                <span aria-hidden="true">/</span>
                @if ($loop->last)
                    <span aria-current="page" class="text-zinc-900">{{ $crumb->name }}</span>
                @else
                    <a href="{{ route('catalog.category', ['path' => $crumb->path]) }}" class="hover:underline">{{ $crumb->name }}</a>
                @endif
            @endforeach
        </nav>
    @endif

    <h1 class="mb-6 text-2xl font-semibold">{{ $heading }}</h1>

    <div class="grid gap-8 lg:grid-cols-4">
        <aside class="lg:col-span-1">
            @if (isset($categoriesByParent[0]))
                <nav aria-label="Categorii" class="mb-6">
                    <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-500">Categorii</h2>
                    <ul class="space-y-1">
                        @include('catalog.partials.category-tree', ['parentId' => 0, 'categoriesByParent' => $categoriesByParent, 'currentPath' => $currentPath])
                    </ul>
                </nav>
            @endif

            <form method="GET" action="{{ $resetUrl }}" class="space-y-5 rounded-xl border border-zinc-200 p-4">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Filtre</h2>

                <div>
                    <label for="q" class="mb-1 block text-sm font-medium">Căutare</label>
                    <input id="q" name="q" type="search" value="{{ $filters['q'] }}" maxlength="100" class="w-full rounded-lg border border-zinc-300 px-3 py-1.5 text-sm">
                </div>

                @if ($brands->isNotEmpty())
                    <fieldset>
                        <legend class="mb-1 text-sm font-medium">Brand</legend>
                        <div class="space-y-1">
                            @foreach ($brands as $brand)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="brand[]" value="{{ $brand->slug }}" class="size-4" @checked(in_array($brand->slug, $filters['brands'], true))>
                                    {{ $brand->name }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                <fieldset>
                    <legend class="mb-1 text-sm font-medium">Preț (lei)</legend>
                    <div class="flex items-center gap-2">
                        <label class="sr-only" for="min">Preț minim</label>
                        <input id="min" name="min" type="number" min="0" inputmode="numeric" value="{{ $filters['min'] }}" placeholder="min" class="w-full rounded-lg border border-zinc-300 px-2 py-1.5 text-sm">
                        <span aria-hidden="true">–</span>
                        <label class="sr-only" for="max">Preț maxim</label>
                        <input id="max" name="max" type="number" min="0" inputmode="numeric" value="{{ $filters['max'] }}" placeholder="max" class="w-full rounded-lg border border-zinc-300 px-2 py-1.5 text-sm">
                    </div>
                </fieldset>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="in_stock" value="1" class="size-4" @checked($filters['in_stock'])> Doar produse în stoc
                </label>

                <div>
                    <label for="sort" class="mb-1 block text-sm font-medium">Sortare</label>
                    <select id="sort" name="sort" class="w-full rounded-lg border border-zinc-300 px-2 py-1.5 text-sm">
                        <option value="newest" @selected($filters['sort'] === 'newest')>Cele mai noi</option>
                        <option value="price_asc" @selected($filters['sort'] === 'price_asc')>Preț crescător</option>
                        <option value="price_desc" @selected($filters['sort'] === 'price_desc')>Preț descrescător</option>
                    </select>
                </div>

                <div class="flex items-center gap-3">
                    <x-button>Aplică</x-button>
                    @if ($filtered)
                        <a href="{{ $resetUrl }}" class="text-sm text-zinc-600 hover:underline">Resetează</a>
                    @endif
                </div>
            </form>
        </aside>

        <section class="lg:col-span-3" aria-label="Produse">
            <p class="mb-4 text-sm text-zinc-500">{{ $products->total() }} {{ $products->total() === 1 ? 'produs' : 'produse' }}</p>

            @if ($products->isEmpty())
                <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center">
                    <p class="text-zinc-600">Nu am găsit produse pentru această selecție.</p>
                    @if ($filtered)
                        <a href="{{ $resetUrl }}" class="mt-3 inline-block text-brand-600 hover:underline">Șterge filtrele</a>
                    @endif
                </div>
            @else
                <ul class="grid grid-cols-2 gap-4 md:grid-cols-3">
                    @foreach ($products as $product)
                        <li><x-product-card :product="$product" /></li>
                    @endforeach
                </ul>

                <div class="mt-8">{{ $products->links() }}</div>
            @endif
        </section>
    </div>
</x-layouts.app>
