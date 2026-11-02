@php
    $heading = $category?->name ?? ($filters['q'] !== '' ? 'Rezultate pentru „'.$filters['q'].'”' : 'Toate produsele');
    $currentPath = $category?->path;
    $noindex = $specs !== [] || $filters['q'] !== '' || $filters['brands'] !== [] || $filters['min'] !== null || $filters['max'] !== null || $filters['in_stock'] || $filters['sort'] !== 'newest' || $products->currentPage() > 1;
    $resetUrl = $category ? route('catalog.category', ['path' => $category->path]) : route('catalog.index');
    $crumbs = [['Acasă', url('/')]];
    if ($breadcrumbs->isEmpty()) {
        $crumbs[] = ['Produse', null];
    } else {
        $crumbs[] = ['Produse', route('catalog.index')];
        foreach ($breadcrumbs->values() as $i => $crumb) {
            $isLast = $i === $breadcrumbs->count() - 1;
            $crumbs[] = [$crumb->name, $isLast ? null : route('catalog.category', ['path' => $crumb->path])];
        }
    }
    $input = 'w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30';
@endphp
<x-layouts.app :title="$category?->meta_title ?: $heading"
               :description="$category?->meta_description"
               :robots="$noindex ? 'noindex,follow' : null">
    <x-breadcrumbs :items="$crumbs" class="mb-3" />

    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $heading }}</h1>
        <p class="mt-1 text-sm text-zinc-500">{{ $products->total() }} {{ $products->total() === 1 ? 'produs găsit' : 'produse găsite' }}</p>
    </div>

    @if ($subcategories->isNotEmpty())
        <ul class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6" aria-label="Subcategorii">
            @foreach ($subcategories as $sub)
                <li>
                    <a href="{{ route('catalog.category', ['path' => $sub->path]) }}"
                       class="group block h-full overflow-hidden rounded-xl border border-zinc-200 bg-white transition hover:border-brand-300 hover:shadow-md">
                        <x-tile-image :path="$sub->image_path" :name="$sub->name" class="aspect-[4/3] w-full" />
                        <span class="block border-t border-zinc-100 px-3 py-2 text-sm font-medium leading-snug group-hover:text-brand-700">{{ $sub->name }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    {{--
        Filtrele pe telefon se deschid cu un checkbox (fără JavaScript): `peer` + `peer-checked`.
        Pe ecran mare bara laterală este mereu vizibilă.
    --}}
    <div class="grid gap-6 lg:grid-cols-[16rem_minmax(0,1fr)] lg:gap-8">
        <input id="filters-toggle" type="checkbox" class="peer sr-only">
        <label for="filters-toggle"
               class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 py-2.5 text-sm font-medium shadow-sm hover:bg-zinc-50 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand-600 lg:hidden">
            <x-ui-icon name="filter" class="size-5" />
            Filtre și categorii
            @if ($chips !== [])<span class="rounded-full bg-brand-600 px-2 text-xs font-semibold text-white" aria-label="{{ count($chips) }} filtre active">{{ count($chips) }}</span>@endif
        </label>

        <aside class="hidden peer-checked:block lg:block">
            @if (isset($categoriesByParent[0]))
                <nav aria-label="Categorii" class="mb-6">
                    <h2 class="mb-2 text-xs font-semibold uppercase tracking-wider text-zinc-500">Categorii</h2>
                    <ul class="space-y-1">
                        @include('catalog.partials.category-tree', ['parentId' => 0, 'categoriesByParent' => $categoriesByParent, 'currentPath' => $currentPath])
                    </ul>
                </nav>
            @endif

            <form method="GET" action="{{ $resetUrl }}" class="space-y-5 rounded-2xl border border-zinc-200 bg-zinc-50 p-4">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Filtre</h2>

                <div>
                    <label for="q" class="mb-1 block text-sm font-medium">Căutare</label>
                    <input id="q" name="q" type="search" value="{{ $filters['q'] }}" maxlength="100" class="{{ $input }}">
                </div>

                @if ($brands->isNotEmpty())
                    <fieldset>
                        <legend class="mb-1 text-sm font-medium">Brand</legend>
                        <div class="space-y-1">
                            @foreach ($brands as $brand)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="brand[]" value="{{ $brand->slug }}" class="size-4 accent-brand-600" @checked(in_array($brand->slug, $filters['brands'], true))>
                                    @if ($brand->logo_path)
                                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($brand->logo_path) }}" alt="" loading="lazy" class="size-7 shrink-0 rounded border border-zinc-200 bg-white object-contain p-0.5">
                                    @endif
                                    <span>{{ $brand->name }} <span class="text-zinc-400">({{ $brandCounts[$brand->id] ?? 0 }})</span></span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                @foreach ($facets as $facet)
                    <fieldset>
                        <legend class="mb-1 text-sm font-medium">{{ $facet['name'] }}</legend>
                        <div class="space-y-1">
                            @foreach ($facet['values'] as $value)
                                @if ($loop->index === 6)
                                    <details class="space-y-1" @if (collect(array_slice($facet['values'], 6))->contains('checked', true)) open @endif>
                                        <summary class="cursor-pointer text-sm text-brand-700 hover:underline">Mai multe</summary>
                                @endif
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="a[{{ $facet['slug'] }}][]" value="{{ $value['slug'] }}" class="size-4 accent-brand-600" @checked($value['checked'])>
                                    <span>{{ $value['value'] }} <span class="text-zinc-400">({{ $value['count'] }})</span></span>
                                </label>
                                @if ($loop->last && $loop->count > 6)
                                    </details>
                                @endif
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach

                {{-- Glisorul apare doar cu JavaScript (data-range-ui); câmpurile min/max funcționează oricum. --}}
                <fieldset data-price-range data-min="{{ $priceBounds['min'] }}" data-max="{{ $priceBounds['max'] }}">
                    <legend class="mb-1 text-sm font-medium">Preț (lei)</legend>
                    <p class="mb-1 text-xs text-zinc-500 empty:hidden" data-range-label aria-live="polite"></p>
                    @if ($priceBounds['max'] > $priceBounds['min'])
                        <div class="range-dual hidden" data-range-ui>
                            <div class="range-track"></div>
                            <input type="range" min="{{ $priceBounds['min'] }}" max="{{ $priceBounds['max'] }}" step="1" value="{{ $filters['min'] ?? $priceBounds['min'] }}" data-range-low aria-label="Preț minim (glisor)">
                            <input type="range" min="{{ $priceBounds['min'] }}" max="{{ $priceBounds['max'] }}" step="1" value="{{ $filters['max'] ?? $priceBounds['max'] }}" data-range-high aria-label="Preț maxim (glisor)">
                        </div>
                    @endif
                    <div class="mt-2 flex items-center gap-2">
                        <label class="sr-only" for="min">Preț minim</label>
                        <input id="min" name="min" type="number" min="0" inputmode="numeric" value="{{ $filters['min'] }}" placeholder="{{ $priceBounds['max'] > $priceBounds['min'] ? $priceBounds['min'] : 'min' }}" data-num-min class="{{ $input }}">
                        <span aria-hidden="true">–</span>
                        <label class="sr-only" for="max">Preț maxim</label>
                        <input id="max" name="max" type="number" min="0" inputmode="numeric" value="{{ $filters['max'] }}" placeholder="{{ $priceBounds['max'] > $priceBounds['min'] ? $priceBounds['max'] : 'max' }}" data-num-max class="{{ $input }}">
                    </div>
                </fieldset>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="in_stock" value="1" class="size-4 accent-brand-600" @checked($filters['in_stock'])> Doar produse în stoc
                </label>

                {{-- Sortarea are controlul ei deasupra listei; aici doar o păstrăm la aplicarea filtrelor. --}}
                @if ($filters['sort'] !== 'newest')
                    <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
                @endif

                <div class="flex items-center gap-3">
                    <x-button class="flex-1 sm:flex-none">Aplică</x-button>
                    @if ($filtered)
                        <a href="{{ $resetUrl }}" class="text-sm text-zinc-600 hover:underline">Resetează</a>
                    @endif
                </div>
            </form>
        </aside>

        <section aria-label="Produse" class="min-w-0">
            @if ($chips !== [])
                <ul class="mb-4 flex flex-wrap items-center gap-2" aria-label="Filtre active">
                    @foreach ($chips as $chip)
                        <li>
                            <a href="{{ $chip['url'] }}" class="inline-flex items-center gap-1.5 rounded-full border border-brand-200 bg-brand-50 py-1 pl-3 pr-2 text-sm font-medium text-brand-800 hover:bg-brand-100">
                                {{ $chip['label'] }}
                                <x-ui-icon name="close" class="size-4" />
                                <span class="sr-only">Elimină filtrul</span>
                            </a>
                        </li>
                    @endforeach
                    <li><a href="{{ $resetUrl }}" class="px-1 text-sm text-zinc-600 hover:underline">Șterge toate</a></li>
                </ul>
            @endif

            @if ($products->isNotEmpty())
                <form method="GET" action="{{ $resetUrl }}" class="mb-4 flex items-center justify-end gap-2">
                    @if ($filters['q'] !== '')<input type="hidden" name="q" value="{{ $filters['q'] }}">@endif
                    @foreach ($filters['brands'] as $slug)<input type="hidden" name="brand[]" value="{{ $slug }}">@endforeach
                    @if ($filters['min'] !== null)<input type="hidden" name="min" value="{{ $filters['min'] }}">@endif
                    @if ($filters['max'] !== null)<input type="hidden" name="max" value="{{ $filters['max'] }}">@endif
                    @if ($filters['in_stock'])<input type="hidden" name="in_stock" value="1">@endif
                    @foreach ($specs as $attributeSlug => $valueSlugs)@foreach ($valueSlugs as $valueSlug)<input type="hidden" name="a[{{ $attributeSlug }}][]" value="{{ $valueSlug }}">@endforeach @endforeach

                    <label for="sort" class="text-sm text-zinc-500">Sortare</label>
                    <select id="sort" name="sort" data-autosubmit class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                        <option value="newest" @selected($filters['sort'] === 'newest')>Cele mai noi</option>
                        <option value="price_asc" @selected($filters['sort'] === 'price_asc')>Preț crescător</option>
                        <option value="price_desc" @selected($filters['sort'] === 'price_desc')>Preț descrescător</option>
                    </select>
                    <noscript><x-button variant="secondary">Aplică</x-button></noscript>
                </form>
            @endif

            @if ($products->isEmpty())
                <div class="rounded-2xl border border-dashed border-zinc-300 p-10 text-center">
                    <x-ui-icon name="search" class="mx-auto size-10 text-zinc-300" />
                    <p class="mt-3 text-zinc-600">Nu am găsit produse pentru această selecție.</p>
                    @if ($filtered)
                        <a href="{{ $resetUrl }}" class="mt-3 inline-block font-medium text-brand-600 hover:underline">Șterge filtrele</a>
                    @endif
                </div>
            @else
                <ul class="grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3">
                    @foreach ($products as $product)
                        <li><x-product-card :product="$product" /></li>
                    @endforeach
                </ul>

                <div class="mt-8">{{ $products->links() }}</div>
            @endif
        </section>
    </div>
</x-layouts.app>
