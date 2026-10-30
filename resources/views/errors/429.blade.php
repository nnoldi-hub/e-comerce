<x-layouts.app title="Prea multe cereri" robots="noindex">
    <div class="mx-auto max-w-lg py-12 text-center">
        <p class="text-5xl font-semibold text-brand-600">429</p>
        <h1 class="mt-4 text-2xl font-semibold">Prea multe cereri</h1>
        <p class="mt-2 text-zinc-600">Ai făcut prea multe cereri într-un timp scurt. Așteaptă puțin și încearcă din nou.</p>
        <div class="mt-6 flex justify-center gap-4 text-sm">
            <a href="{{ url('/') }}" class="rounded-lg bg-brand-600 px-4 py-2 font-medium text-white hover:bg-brand-700">Pagina principală</a>
            <a href="{{ route('catalog.index') }}" class="rounded-lg border border-zinc-300 px-4 py-2 hover:bg-zinc-50">Vezi produsele</a>
        </div>
    </div>
</x-layouts.app>
