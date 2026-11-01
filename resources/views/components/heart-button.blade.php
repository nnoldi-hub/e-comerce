@props(['product', 'wished' => false])
@php
    $heart = 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z';
    $label = $wished ? 'Scoate din favorite' : 'Adaugă la favorite';
    $classes = 'flex size-9 items-center justify-center rounded-full bg-white/90 shadow ring-1 ring-black/5 transition hover:bg-white '.($wished ? 'text-red-600' : 'text-zinc-500 hover:text-red-600');
@endphp
{{-- Pentru vizitatori e un link spre autentificare; pentru clienți, un formular POST (deasupra linkului întins al cardului). --}}
<div {{ $attributes->class(['z-10']) }}>
    @auth
        <form method="POST" action="{{ route('account.wishlist.toggle', $product) }}">
            @csrf
            <button type="submit" class="{{ $classes }}" aria-label="{{ $label }}" aria-pressed="{{ $wished ? 'true' : 'false' }}" title="{{ $label }}">
                <svg viewBox="0 0 24 24" class="size-5" fill="{{ $wished ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><path d="{{ $heart }}"/></svg>
            </button>
        </form>
    @else
        <a href="{{ route('login') }}" class="{{ $classes }}" aria-label="Autentifică-te pentru a salva la favorite" title="Autentifică-te pentru a salva la favorite">
            <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><path d="{{ $heart }}"/></svg>
        </a>
    @endauth
</div>
