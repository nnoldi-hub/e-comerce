<x-layouts.app title="Pagina nu a fost găsită" robots="noindex">
    <div class="mx-auto max-w-lg py-12 text-center">
        <p class="text-5xl font-semibold text-brand-600">404</p>
        <h1 class="mt-4 text-2xl font-semibold">Pagina nu a fost găsită</h1>
        <p class="mt-2 text-zinc-600">Adresa nu există sau a fost mutată. Verifică linkul sau caută în magazin.</p>
        <div class="mt-6 flex justify-center gap-4 text-sm">
            <a href="{{ url('/') }}" class="rounded-lg bg-brand-600 px-4 py-2 font-medium text-white hover:bg-brand-700">Pagina principală</a>
            <a href="{{ route('catalog.index') }}" class="rounded-lg border border-zinc-300 px-4 py-2 hover:bg-zinc-50">Vezi produsele</a>
        </div>
    </div>
</x-layouts.app>
