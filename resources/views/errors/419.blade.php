<x-layouts.app title="Sesiunea a expirat" robots="noindex">
    <div class="mx-auto max-w-lg py-12 text-center">
        <p class="text-5xl font-semibold text-brand-600">419</p>
        <h1 class="mt-4 text-2xl font-semibold">Sesiunea a expirat</h1>
        <p class="mt-2 text-zinc-600">Pagina a stat deschisă prea mult. Reîncarcă pagina și încearcă din nou.</p>
        <div class="mt-6 flex justify-center gap-4 text-sm">
            <a href="{{ url('/') }}" class="rounded-lg bg-brand-600 px-4 py-2 font-medium text-white hover:bg-brand-700">Pagina principală</a>
            <a href="{{ route('catalog.index') }}" class="rounded-lg border border-zinc-300 px-4 py-2 hover:bg-zinc-50">Vezi produsele</a>
        </div>
    </div>
</x-layouts.app>
