@props(['title' => null, 'description' => null, 'robots' => null, 'head' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · '.config('app.name') : config('app.name') }}</title>
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif
    @if ($robots)
        <meta name="robots" content="{{ $robots }}">
    @endif
    <link rel="canonical" href="{{ url()->current() }}">
    {{ $head }}

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-full flex flex-col bg-white text-zinc-900 antialiased">
    <a href="#continut" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">Sari la conținut</a>

    <header class="border-b border-zinc-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4">
            <a href="{{ url('/') }}" class="text-lg font-semibold tracking-tight">{{ config('app.name') }}</a>

            <nav class="hidden items-center gap-6 text-sm sm:flex" aria-label="Principal">
                <a href="{{ url('/') }}" class="hover:text-brand-600">Acasă</a>
                <a href="{{ route('catalog.index') }}" class="hover:text-brand-600">Produse</a>
                <form method="GET" action="{{ route('catalog.index') }}" role="search" class="flex">
                    <label for="q-desktop" class="sr-only">Caută produse</label>
                    <input id="q-desktop" name="q" type="search" value="{{ request('q') }}" placeholder="Caută…" maxlength="100"
                           class="w-40 rounded-lg border border-zinc-300 px-3 py-1.5 text-sm">
                </form>
                <a href="{{ route('cart.index') }}" class="hover:text-brand-600">Coș</a>
                @auth
                    <a href="{{ route('account.index') }}" class="hover:text-brand-600">Contul meu</a>
                    <form method="POST" action="{{ url('/logout') }}">
                        @csrf
                        <button type="submit" class="hover:text-brand-600">Ieși din cont</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="hover:text-brand-600">Autentificare</a>
                @endauth
            </nav>

            <details class="relative sm:hidden">
                <summary class="cursor-pointer list-none rounded border border-zinc-300 px-3 py-1.5 text-sm">Meniu</summary>
                <div class="absolute right-0 z-40 mt-2 w-48 rounded-lg border border-zinc-200 bg-white p-2 text-sm shadow-lg">
                    <a href="{{ url('/') }}" class="block rounded px-3 py-2 hover:bg-zinc-50">Acasă</a>
                    <a href="{{ route('catalog.index') }}" class="block rounded px-3 py-2 hover:bg-zinc-50">Produse</a>
                    <a href="{{ route('cart.index') }}" class="block rounded px-3 py-2 hover:bg-zinc-50">Coș</a>
                    @auth
                        <a href="{{ route('account.index') }}" class="block rounded px-3 py-2 hover:bg-zinc-50">Contul meu</a>
                        <form method="POST" action="{{ url('/logout') }}">
                            @csrf
                            <button type="submit" class="block w-full rounded px-3 py-2 text-left hover:bg-zinc-50">Ieși din cont</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="block rounded px-3 py-2 hover:bg-zinc-50">Autentificare</a>
                    @endauth
                </div>
            </details>
        </div>
    </header>

    <main id="continut" class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
        <x-flash />
        {{ $slot }}
    </main>

    <footer class="border-t border-zinc-200 bg-zinc-50">
        <div class="mx-auto max-w-6xl px-4 py-6 text-sm text-zinc-500">
            @php($footerPages = \App\Models\ContentPage::footerLinks())
            @if ($footerPages !== [])
                <nav aria-label="Informații" class="mb-3">
                    <ul class="flex flex-wrap gap-x-5 gap-y-1">
                        @foreach ($footerPages as $footerPage)
                            <li><a href="{{ route('pages.show', $footerPage['slug']) }}" class="hover:text-brand-600 hover:underline">{{ $footerPage['title'] }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif
            © {{ date('Y') }} {{ config('app.name') }}. Toate drepturile rezervate.
        </div>
    </footer>
</body>
</html>
