@php
    $gallery = $product->getMedia('gallery');
    $thumb = $product->getFirstMedia('thumbnail');
    $images = $thumb ? $gallery->prepend($thumb) : $gallery;

    $label = fn ($v) => $v->attributeValues->sortBy('attribute_id')->pluck('value')->implode(' / ') ?: 'Standard';
    $purchasable = $variant && ($variant->allow_backorder || ! $variant->track_inventory || $variant->available_stock > 0);
    $onSale = $variant && $variant->compare_at_price !== null && $variant->compare_at_price > $variant->price;
    $discount = $onSale ? (int) round(100 - ($variant->price * 100 / $variant->compare_at_price)) : 0;
    $crumbs = [['Acasă', url('/')], ['Produse', route('catalog.index')]];
    $firstCategory = $product->categories->first();
    if ($firstCategory !== null) {
        $crumbs[] = [$firstCategory->name, route('catalog.category', ['path' => $firstCategory->path])];
    }
    $crumbs[] = [$product->name, null];
@endphp
<x-layouts.app :title="$metaTitle" :description="$metaDescription">
    <x-slot:head>
        <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    </x-slot:head>

    <x-breadcrumbs :items="$crumbs" class="mb-4" />

    {{-- Pe telefon bara „Adaugă în coș” este fixată jos; spațiul de mai jos o ferește de conținut. --}}
    <div class="grid gap-8 pb-28 md:grid-cols-2 md:gap-10 md:pb-0 lg:gap-14">
        <div class="min-w-0">
            @if ($images->isNotEmpty())
                {{-- Galerie fără JavaScript: derulare orizontală cu snap (glisare pe telefon), miniaturile sunt ancore. --}}
                <div class="no-scrollbar flex snap-x snap-mandatory overflow-x-auto rounded-2xl border border-zinc-200 bg-zinc-50"
                     role="region" aria-label="Galerie de imagini" tabindex="0">
                    @foreach ($images as $image)
                        <img id="slide-{{ $loop->iteration }}"
                             src="{{ $image->hasGeneratedConversion('large') ? $image->getUrl('large') : $image->getUrl() }}"
                             alt="{{ $product->name }}@if ($images->count() > 1) – imaginea {{ $loop->iteration }}@endif"
                             @if (! $loop->first) loading="lazy" @endif
                             class="aspect-square w-full shrink-0 snap-center object-cover">
                    @endforeach
                </div>

                @if ($images->count() > 1)
                    <ul class="mt-3 grid grid-cols-5 gap-2">
                        @foreach ($images as $image)
                            <li>
                                <a href="#slide-{{ $loop->iteration }}" class="block overflow-hidden rounded-lg border border-zinc-200 hover:border-brand-500">
                                    <img src="{{ $image->hasGeneratedConversion('thumb') ? $image->getUrl('thumb') : $image->getUrl() }}"
                                         alt="Vezi imaginea {{ $loop->iteration }}" loading="lazy"
                                         class="aspect-square w-full object-cover">
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @else
                <x-product-image :product="$product" class="aspect-square w-full rounded-2xl" />
            @endif
        </div>

        <div>
            @if ($product->brand)
                <p class="text-sm font-medium uppercase tracking-wide text-brand-700">{{ $product->brand->name }}</p>
            @endif
            <div class="mt-1 flex items-start justify-between gap-3">
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $product->name }}</h1>
                <x-heart-button :product="$product" :wished="$wishlisted" class="shrink-0" />
            </div>

            @if ((int) data_get($product, 'reviews_count', 0) > 0)
                <a href="#recenzii" class="mt-2 inline-flex hover:underline">
                    <x-rating-stars :rating="(float) data_get($product, 'rating_avg', 0)" :count="(int) data_get($product, 'reviews_count', 0)" />
                </a>
            @endif

            @if ($variant)
                <div class="mt-4 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <p class="text-3xl font-bold"><x-price :amount="$variant->price" /></p>
                    @if ($onSale)
                        <p class="text-lg text-zinc-400 line-through"><x-price :amount="$variant->compare_at_price" /></p>
                        <span class="rounded-full bg-red-50 px-2.5 py-0.5 text-sm font-semibold text-red-700">−{{ $discount }}%</span>
                    @endif
                </div>
                <p class="mt-1 text-xs text-zinc-500">SKU: {{ $variant->sku }}</p>

                @if ($variants->count() > 1)
                    <fieldset class="mt-6">
                        <legend class="mb-2 text-sm font-medium">Variantă</legend>
                        <ul class="flex flex-wrap gap-2">
                            @foreach ($variants as $option)
                                <li>
                                    <a href="{{ route('catalog.product', ['product' => $product, 'variant' => $option->id]) }}"
                                       @if ($option->id === $variant->id) aria-current="true" @endif
                                       class="inline-block rounded-lg border px-3.5 py-2 text-sm {{ $option->id === $variant->id ? 'border-brand-600 bg-brand-50 font-semibold text-brand-700 ring-1 ring-brand-600' : 'border-zinc-300 hover:border-zinc-400' }}">
                                        {{ $label($option) }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </fieldset>
                @endif

                <p class="mt-6 flex items-center gap-2 text-sm font-medium {{ $purchasable ? 'text-green-700' : 'text-red-700' }}">
                    <span class="size-2 rounded-full {{ $purchasable ? 'bg-green-600' : 'bg-red-600' }}" aria-hidden="true"></span>
                    {{ $purchasable ? 'În stoc' : 'Stoc epuizat' }}
                </p>

                <form method="POST" action="{{ route('cart.add', $variant) }}"
                      class="fixed inset-x-0 bottom-0 z-30 flex items-center gap-3 border-t border-zinc-200 bg-white/95 p-3 shadow-[0_-8px_24px_rgba(0,0,0,0.08)] backdrop-blur md:static md:mt-4 md:items-end md:border-0 md:bg-transparent md:p-0 md:shadow-none">
                    @csrf
                    <p class="shrink-0 text-lg font-bold md:hidden"><x-price :amount="$variant->price" /></p>
                    <div class="w-20 shrink-0 md:w-24">
                        <label for="quantity" class="sr-only md:not-sr-only md:mb-1 md:block md:text-sm md:font-medium">Cantitate</label>
                        <input id="quantity" name="quantity" type="number" min="1" max="99" value="1"
                               class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30"
                               @disabled(! $purchasable)>
                    </div>
                    <x-button variant="cta" class="min-w-0 flex-1 px-6 py-2.5 md:flex-none" :disabled="! $purchasable">
                        <x-ui-icon name="cart" class="size-5" /> Adaugă în coș
                    </x-button>
                </form>

                <ul class="mt-8 grid gap-3 rounded-2xl bg-zinc-50 p-4 text-sm text-zinc-600">
                    <li class="flex items-center gap-3"><x-ui-icon name="truck" class="size-5 shrink-0 text-brand-700" /> Livrare în România</li>
                    <li class="flex items-center gap-3"><x-ui-icon name="card" class="size-5 shrink-0 text-brand-700" /> Plată cu cardul sau ramburs</li>
                    <li class="flex items-center gap-3"><x-ui-icon name="shield" class="size-5 shrink-0 text-brand-700" /> Comandă sigură</li>
                </ul>
            @else
                <p class="mt-4 text-zinc-600">Produsul nu este disponibil momentan.</p>
            @endif

            @if ($product->description)
                <div class="mt-8 border-t border-zinc-200 pt-6">
                    <h2 class="mb-2 text-lg font-semibold">Descriere</h2>
                    <div class="whitespace-pre-line text-sm leading-relaxed text-zinc-700">{{ strip_tags((string) $product->description) }}</div>
                </div>
            @endif
        </div>
    </div>

    @include('catalog.partials.reviews', ['product' => $product, 'reviews' => $reviews, 'distribution' => $distribution, 'myReview' => $myReview])
</x-layouts.app>
