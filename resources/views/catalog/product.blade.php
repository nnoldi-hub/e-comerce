@php
    $gallery = $product->getMedia('gallery');
    $thumb = $product->getFirstMedia('thumbnail');
    $images = $thumb ? $gallery->prepend($thumb) : $gallery;
    $main = $images->first();
    $mainUrl = $main ? ($main->hasGeneratedConversion('large') ? $main->getUrl('large') : $main->getUrl()) : null;

    $label = fn ($v) => $v->attributeValues->sortBy('attribute_id')->pluck('value')->implode(' / ') ?: 'Standard';
    $purchasable = $variant && ($variant->allow_backorder || ! $variant->track_inventory || $variant->available_stock > 0);
    $onSale = $variant && $variant->compare_at_price !== null && $variant->compare_at_price > $variant->price;
@endphp
<x-layouts.app :title="$metaTitle" :description="$metaDescription">
    <x-slot:head>
        <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    </x-slot:head>

    <nav aria-label="Breadcrumb" class="mb-4 text-sm text-zinc-500">
        <a href="{{ route('catalog.index') }}" class="hover:underline">Produse</a>
        @if ($product->categories->isNotEmpty())
            @php($cat = $product->categories->first())
            <span aria-hidden="true">/</span>
            <a href="{{ route('catalog.category', ['path' => $cat->path]) }}" class="hover:underline">{{ $cat->name }}</a>
        @endif
        <span aria-hidden="true">/</span>
        <span aria-current="page" class="text-zinc-900">{{ $product->name }}</span>
    </nav>

    <div class="grid gap-8 md:grid-cols-2">
        <div>
            @if ($mainUrl)
                <img src="{{ $mainUrl }}" alt="{{ $product->name }}" class="w-full rounded-xl border border-zinc-200 object-cover">
                @if ($images->count() > 1)
                    <ul class="mt-3 grid grid-cols-5 gap-2">
                        @foreach ($images as $image)
                            <li>
                                <a href="{{ $image->getUrl() }}" target="_blank" rel="noopener">
                                    <img src="{{ $image->hasGeneratedConversion('thumb') ? $image->getUrl('thumb') : $image->getUrl() }}"
                                         alt="{{ $product->name }} – imaginea {{ $loop->iteration }}" loading="lazy"
                                         class="aspect-square w-full rounded-lg border border-zinc-200 object-cover">
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @else
                <x-product-image :product="$product" class="aspect-square w-full rounded-xl" />
            @endif
        </div>

        <div>
            @if ($product->brand)
                <p class="text-sm text-zinc-500">{{ $product->brand->name }}</p>
            @endif
            <h1 class="text-2xl font-semibold">{{ $product->name }}</h1>

            @if ($variant)
                <p class="mt-3 text-2xl font-semibold">
                    <x-price :amount="$variant->price" />
                    @if ($onSale)
                        <span class="ml-2 text-base font-normal text-zinc-400 line-through"><x-price :amount="$variant->compare_at_price" /></span>
                    @endif
                </p>
                <p class="mt-1 text-xs text-zinc-500">SKU: {{ $variant->sku }}</p>

                @if ($variants->count() > 1)
                    <fieldset class="mt-5">
                        <legend class="mb-2 text-sm font-medium">Variantă</legend>
                        <ul class="flex flex-wrap gap-2">
                            @foreach ($variants as $option)
                                <li>
                                    <a href="{{ route('catalog.product', ['product' => $product, 'variant' => $option->id]) }}"
                                       @if ($option->id === $variant->id) aria-current="true" @endif
                                       class="inline-block rounded-lg border px-3 py-1.5 text-sm {{ $option->id === $variant->id ? 'border-brand-600 bg-brand-50 font-medium text-brand-700' : 'border-zinc-300 hover:border-zinc-400' }}">
                                        {{ $label($option) }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </fieldset>
                @endif

                <p class="mt-5 text-sm {{ $purchasable ? 'text-green-700' : 'text-red-700' }}">
                    {{ $purchasable ? 'În stoc' : 'Stoc epuizat' }}
                </p>

                <form method="POST" action="{{ route('cart.add', $variant) }}" class="mt-4 flex items-end gap-3">
                    @csrf
                    <div>
                        <label for="quantity" class="mb-1 block text-sm font-medium">Cantitate</label>
                        <input id="quantity" name="quantity" type="number" min="1" max="99" value="1" class="w-24 rounded-lg border border-zinc-300 px-3 py-2 text-sm" @disabled(! $purchasable)>
                    </div>
                    <x-button class="px-6 py-2.5" :disabled="! $purchasable">Adaugă în coș</x-button>
                </form>
            @else
                <p class="mt-4 text-zinc-600">Produsul nu este disponibil momentan.</p>
            @endif

            @if ($product->description)
                <div class="mt-8">
                    <h2 class="mb-2 text-lg font-semibold">Descriere</h2>
                    <div class="whitespace-pre-line text-sm leading-relaxed text-zinc-700">{{ strip_tags((string) $product->description) }}</div>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
