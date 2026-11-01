@php
    $chrome = app(\App\Support\ShopChrome::class);
    $categories = $chrome->categories();
    $heroProducts = $products->take(4);
    $contactUrl = \App\Models\ContentPage::urlFor('contact');
    $benefits = $chrome->benefits();
@endphp
<x-layouts.app :description="'Descoperă produsele '.config('app.name').'.'" :bleed="true">
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

    {{-- Hero --}}
    <section class="relative isolate mt-4 overflow-hidden bg-gradient-to-br from-brand-900 via-brand-800 to-brand-600 text-white sm:mx-4 sm:rounded-3xl lg:mx-8">
        <div class="pointer-events-none absolute -right-24 -top-24 -z-10 size-96 rounded-full bg-white/10 blur-2xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-32 left-1/4 -z-10 size-80 rounded-full bg-brand-500/30 blur-3xl" aria-hidden="true"></div>

        <div class="mx-auto grid max-w-7xl items-center gap-10 px-5 py-12 sm:px-10 sm:py-16 lg:grid-cols-2 lg:gap-14 lg:py-20">
            <div>
                <p class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-brand-100 ring-1 ring-white/20">
                    <x-ui-icon name="check" class="size-4" /> Magazin online
                </p>
                <h1 class="mt-5 text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl">Bun venit la {{ config('app.name') }}</h1>
                <p class="mt-5 max-w-xl text-lg leading-relaxed text-brand-100">Produse alese cu grijă, livrare rapidă și plată sigură, cu cardul sau ramburs.</p>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <x-button-link :href="route('catalog.index')" variant="light" class="px-6 py-3 text-base">
                        Descoperă produsele <x-ui-icon name="arrow" class="size-5" />
                    </x-button-link>
                    @guest
                        <x-button-link :href="url('/register')" variant="outline-light" class="px-6 py-3 text-base">Creează cont</x-button-link>
                    @endguest
                </div>
            </div>

            @if ($heroProducts->isNotEmpty())
                <ul class="hidden grid-cols-2 gap-4 lg:grid" aria-label="Produse în vitrină">
                    @foreach ($heroProducts as $heroProduct)
                        <li @class(['translate-y-6' => $loop->even])>
                            <a href="{{ route('catalog.product', $heroProduct) }}" class="group block overflow-hidden rounded-2xl bg-white text-zinc-900 shadow-xl ring-1 ring-black/5 transition hover:-translate-y-1">
                                <x-product-image :product="$heroProduct" class="aspect-square w-full" />
                                <span class="block truncate px-4 py-3 text-sm font-medium group-hover:text-brand-700">{{ $heroProduct->name }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    {{-- Avantaje --}}
    @if ($benefits !== [])
        <section class="shell py-10 sm:py-12" aria-label="De ce să cumperi de la noi">
            <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($benefits as $benefit)
                    <li class="flex items-start gap-4 rounded-2xl border border-zinc-200 bg-white p-4">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                            <x-ui-icon :name="$benefit['icon']" class="size-6" />
                        </span>
                        <div>
                            <h2 class="text-sm font-semibold">
                                @if ($benefit['url'])<a href="{{ $benefit['url'] }}" class="hover:underline">{{ $benefit['title'] }}</a>@else{{ $benefit['title'] }}@endif
                            </h2>
                            <p class="mt-0.5 text-sm text-zinc-500">{{ $benefit['text'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Categorii --}}
    @if ($categories->isNotEmpty())
        <section class="shell pb-10 sm:pb-12" aria-labelledby="h-categorii">
            <div class="mb-5 flex items-baseline justify-between">
                <h2 id="h-categorii" class="text-2xl font-bold tracking-tight">Categorii</h2>
                <a href="{{ route('catalog.index') }}" class="text-sm font-medium text-brand-600 hover:underline">Toate produsele</a>
            </div>
            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ($categories as $category)
                    <li>
                        <a href="{{ route('catalog.category', ['path' => $category->path]) }}"
                           class="group flex h-full flex-col justify-between gap-6 rounded-2xl border border-zinc-200 bg-zinc-50 p-4 transition hover:border-brand-200 hover:bg-brand-50">
                            <span class="flex size-10 items-center justify-center rounded-xl bg-white text-lg font-bold text-brand-700 shadow-sm" aria-hidden="true">{{ mb_strtoupper(mb_substr($category->name, 0, 1)) }}</span>
                            <span class="flex items-center justify-between gap-2 text-sm font-semibold">
                                {{ $category->name }}
                                <x-ui-icon name="chevron" class="size-4 shrink-0 text-zinc-400 transition group-hover:translate-x-0.5 group-hover:text-brand-600" />
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Produse noi --}}
    <section class="shell pb-12 sm:pb-16" aria-labelledby="h-noi">
        <div class="mb-5 flex items-baseline justify-between">
            <h2 id="h-noi" class="text-2xl font-bold tracking-tight">Produse noi</h2>
            <a href="{{ route('catalog.index') }}" class="text-sm font-medium text-brand-600 hover:underline">Vezi toate produsele</a>
        </div>

        @if ($products->isEmpty())
            <p class="rounded-2xl border border-dashed border-zinc-300 p-10 text-center text-zinc-500">Momentan nu avem produse publicate. Revino în curând.</p>
        @else
            <ul class="grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3 lg:grid-cols-4">
                @foreach ($products as $product)
                    <li><x-product-card :product="$product" /></li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Contact --}}
    @if ($contactUrl)
        <section class="shell pb-12 sm:pb-16">
            <div class="flex flex-col items-start justify-between gap-4 rounded-3xl bg-brand-50 p-6 sm:flex-row sm:items-center sm:p-10">
                <div>
                    <h2 class="text-xl font-bold tracking-tight">Ai nevoie de ajutor la comandă?</h2>
                    <p class="mt-1 text-zinc-600">Scrie-ne și îți răspundem cât de repede putem.</p>
                </div>
                <x-button-link :href="$contactUrl">Contact</x-button-link>
            </div>
        </section>
    @endif
</x-layouts.app>
