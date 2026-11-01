@props(['title' => null, 'description' => null, 'robots' => null, 'head' => null, 'bleed' => false])
@php
    $chrome = app(\App\Support\ShopChrome::class);
    $navCategories = $chrome->categories();
    $cartCount = $chrome->cartCount();
    $cartLabel = $cartCount > 0 ? 'Coș, '.$cartCount.' '.($cartCount === 1 ? 'produs' : 'produse') : 'Coș';
    $pageTitle = $title ? $title.' · '.config('app.name') : config('app.name');
    $footerPages = \App\Models\ContentPage::footerLinks();
    $benefits = $chrome->benefits();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2563eb">
    <title>{{ $pageTitle }}</title>
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif
    @if ($robots)
        <meta name="robots" content="{{ $robots }}">
    @endif
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $pageTitle }}">
    @if ($description)
        <meta property="og:description" content="{{ $description }}">
    @endif
    <meta property="og:url" content="{{ url()->current() }}">
    {{ $head }}

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="flex min-h-full flex-col bg-slate-50 text-zinc-900 antialiased">
    <a href="#continut" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">Sari la conținut</a>

    {{-- Bara de beneficii (doar desktop); textele se editează în config/storefront.php --}}
    @if ($benefits !== [])
        <div class="hidden bg-brand-950 text-xs text-brand-100 md:block">
            <ul class="shell flex items-center justify-between gap-6 py-2">
                @foreach ($benefits as $benefit)
                    <li class="flex items-center gap-2">
                        <x-ui-icon :name="$benefit['icon']" class="size-4 text-brand-200" />
                        @if ($benefit['url'])
                            <a href="{{ $benefit['url'] }}" class="hover:text-white hover:underline">{{ $benefit['short'] }}</a>
                        @else
                            {{ $benefit['short'] }}
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <header class="sticky top-0 z-40 border-b border-zinc-200 bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/85">
        <div class="shell flex items-center gap-2 py-3 sm:gap-4 lg:gap-8">
            {{-- Meniu mobil: <details>, fără JavaScript. Panoul se întinde pe toată lățimea headerului. --}}
            <details class="group md:hidden">
                <summary class="flex size-10 cursor-pointer list-none items-center justify-center rounded-lg text-zinc-700 hover:bg-zinc-100 [&::-webkit-details-marker]:hidden" aria-label="Meniu">
                    <x-ui-icon name="menu" class="size-6 group-open:hidden" />
                    <x-ui-icon name="close" class="hidden size-6 group-open:block" />
                    <span class="sr-only">Meniu</span>
                </summary>
                <div class="absolute inset-x-0 top-full max-h-[calc(100dvh-4.5rem)] overflow-y-auto border-b border-zinc-200 bg-white shadow-xl">
                    <nav class="shell py-3 text-base" aria-label="Meniu mobil">
                        <a href="{{ url('/') }}" class="block rounded-lg px-3 py-3 hover:bg-zinc-50">Acasă</a>
                        <a href="{{ route('catalog.index') }}" class="block rounded-lg px-3 py-3 font-medium hover:bg-zinc-50">Produse</a>
                        @foreach ($navCategories as $navCategory)
                            <a href="{{ route('catalog.category', ['path' => $navCategory->path]) }}" class="block rounded-lg px-3 py-3 pl-7 text-zinc-600 hover:bg-zinc-50">{{ $navCategory->name }}</a>
                        @endforeach
                        <a href="{{ route('cart.index') }}" class="block rounded-lg px-3 py-3 hover:bg-zinc-50">Coș</a>
                        <div class="my-2 border-t border-zinc-100"></div>
                        @auth
                            <a href="{{ route('account.index') }}" class="block rounded-lg px-3 py-3 hover:bg-zinc-50">Contul meu</a>
                            <form method="POST" action="{{ url('/logout') }}">
                                @csrf
                                <button type="submit" class="block w-full rounded-lg px-3 py-3 text-left hover:bg-zinc-50">Ieși din cont</button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="block rounded-lg px-3 py-3 hover:bg-zinc-50">Autentificare</a>
                        @endauth
                    </nav>
                </div>
            </details>

            <a href="{{ url('/') }}" class="shrink-0 text-zinc-900" aria-label="{{ config('app.name') }} – pagina principală">
                <x-logo />
            </a>

            <form method="GET" action="{{ route('catalog.index') }}" role="search" class="mx-auto hidden w-full max-w-xl md:block" data-search-suggest data-suggest-url="{{ route('search.suggest') }}">
                <label for="q-desktop" class="sr-only">Caută produse</label>
                <div class="relative">
                    <x-ui-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-zinc-400" />
                    <input id="q-desktop" name="q" type="search" value="{{ request('q') }}" placeholder="Caută produse…" maxlength="100" autocomplete="off"
                           class="w-full rounded-full border border-zinc-300 bg-zinc-50 py-2 pl-10 pr-4 text-sm transition focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                </div>
            </form>

            <div class="ml-auto flex items-center gap-1 md:ml-0">
                @auth
                    <a href="{{ route('account.index') }}" class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm text-zinc-700 hover:bg-zinc-100">
                        <x-ui-icon name="user" class="size-6" />
                        <span class="hidden lg:inline">Contul meu</span>
                        <span class="sr-only lg:hidden">Contul meu</span>
                    </a>
                    <form method="POST" action="{{ url('/logout') }}" class="hidden lg:block">
                        @csrf
                        <button type="submit" class="rounded-lg px-2.5 py-2 text-sm text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900">Ieși din cont</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="hidden items-center gap-2 rounded-lg px-2.5 py-2 text-sm text-zinc-700 hover:bg-zinc-100 sm:flex">
                        <x-ui-icon name="user" class="size-6" />
                        <span class="hidden lg:inline">Autentificare</span>
                        <span class="sr-only lg:hidden">Autentificare</span>
                    </a>
                @endauth

                <a href="{{ route('cart.index') }}" class="relative flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm text-zinc-700 hover:bg-zinc-100" aria-label="{{ $cartLabel }}">
                    <x-ui-icon name="cart" class="size-6" />
                    <span class="hidden lg:inline">Coș</span>
                    @if ($cartCount > 0)
                        <span class="absolute -right-0.5 -top-0.5 flex min-w-5 items-center justify-center rounded-full bg-brand-600 px-1.5 text-[11px] font-semibold leading-5 text-white lg:static lg:ml-0">{{ $cartCount > 99 ? '99+' : $cartCount }}</span>
                    @endif
                </a>
            </div>
        </div>

        {{-- Căutare pe telefon --}}
        <form method="GET" action="{{ route('catalog.index') }}" role="search" class="shell pb-3 md:hidden" data-search-suggest data-suggest-url="{{ route('search.suggest') }}">
            <label for="q-mobile" class="sr-only">Caută produse</label>
            <div class="relative">
                <x-ui-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-zinc-400" />
                <input id="q-mobile" name="q" type="search" value="{{ request('q') }}" placeholder="Caută produse…" maxlength="100" autocomplete="off"
                       class="w-full rounded-full border border-zinc-300 bg-zinc-50 py-2.5 pl-10 pr-4 text-sm focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/30">
            </div>
        </form>

        {{-- Categorii (desktop) --}}
        <nav class="hidden border-t border-zinc-100 md:block" aria-label="Principal">
            <div class="shell no-scrollbar flex items-center gap-1 overflow-x-auto py-1.5 text-sm">
                <a href="{{ route('catalog.index') }}" @if (request()->routeIs('catalog.index')) aria-current="page" @endif
                   class="whitespace-nowrap rounded-lg px-3 py-1.5 font-medium hover:bg-zinc-100 {{ request()->routeIs('catalog.index') ? 'text-brand-700' : 'text-zinc-800' }}">Toate produsele</a>
                @foreach ($navCategories as $navCategory)
                    <a href="{{ route('catalog.category', ['path' => $navCategory->path]) }}"
                       class="whitespace-nowrap rounded-lg px-3 py-1.5 text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900">{{ $navCategory->name }}</a>
                @endforeach
            </div>
        </nav>
    </header>

    @if ($bleed)
        <main id="continut" class="flex-1">
            <div class="shell pt-4"><x-flash /></div>
            {{ $slot }}
        </main>
    @else
        <main id="continut" class="shell w-full flex-1 py-6 sm:py-10">
            <x-flash />
            {{ $slot }}
        </main>
    @endif

    <footer class="mt-12 bg-zinc-950 text-zinc-300">
        <div class="shell grid gap-10 py-12 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2 lg:col-span-1">
                <a href="{{ url('/') }}" class="text-white"><x-logo :light="true" /></a>
                <p class="mt-4 max-w-xs text-sm leading-relaxed text-zinc-400">Produse alese cu grijă, comandate simplu și plătite sigur, cu cardul sau ramburs.</p>
            </div>

            <nav aria-label="Magazin">
                <h2 class="text-sm font-semibold text-white">Magazin</h2>
                <ul class="mt-4 space-y-2 text-sm">
                    <li><a href="{{ route('catalog.index') }}" class="hover:text-white">Toate produsele</a></li>
                    @foreach ($navCategories as $navCategory)
                        <li><a href="{{ route('catalog.category', ['path' => $navCategory->path]) }}" class="hover:text-white">{{ $navCategory->name }}</a></li>
                    @endforeach
                </ul>
            </nav>

            <nav aria-label="Contul meu">
                <h2 class="text-sm font-semibold text-white">Contul tău</h2>
                <ul class="mt-4 space-y-2 text-sm">
                    @auth
                        <li><a href="{{ route('account.index') }}" class="hover:text-white">Contul meu</a></li>
                        <li><a href="{{ route('account.orders.index') }}" class="hover:text-white">Comenzile mele</a></li>
                    @else
                        <li><a href="{{ route('login') }}" class="hover:text-white">Autentificare</a></li>
                        <li><a href="{{ url('/register') }}" class="hover:text-white">Creează cont</a></li>
                    @endauth
                    <li><a href="{{ route('cart.index') }}" class="hover:text-white">Coșul de cumpărături</a></li>
                </ul>
            </nav>

            @if ($footerPages !== [])
                <nav aria-label="Informații">
                    <h2 class="text-sm font-semibold text-white">Informații</h2>
                    <ul class="mt-4 space-y-2 text-sm">
                        @foreach ($footerPages as $footerPage)
                            <li><a href="{{ route('pages.show', $footerPage['slug']) }}" class="hover:text-white hover:underline">{{ $footerPage['title'] }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </div>

        <div class="border-t border-white/10">
            <div class="shell flex flex-col gap-2 py-5 text-xs text-zinc-500 sm:flex-row sm:items-center sm:justify-between">
                <p>© {{ date('Y') }} {{ config('app.name') }}. Toate drepturile rezervate.</p>
                <p class="flex items-center gap-1.5"><x-ui-icon name="lock" class="size-4" /> Plată securizată</p>
            </div>
        </div>
    </footer>
</body>
</html>
