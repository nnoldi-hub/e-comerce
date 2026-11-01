{{-- Același aspect ca <x-button>, dar ca link. `cta` (portocaliu, acțiuni de cumpărare), `light` și `outline-light` sunt pentru fundaluri închise (hero, footer). --}}
@props(['href', 'variant' => 'primary'])
<a href="{{ $href }}" {{ $attributes->class([
    'inline-flex items-center justify-center gap-2 rounded-lg px-5 py-2.5 text-sm font-semibold transition',
    'bg-brand-600 text-white shadow-sm hover:bg-brand-700 active:bg-brand-800' => $variant === 'primary',
    'border border-zinc-300 bg-white text-zinc-900 hover:bg-zinc-50' => $variant === 'secondary',
    'bg-accent-700 text-white shadow-sm hover:bg-accent-800 active:bg-accent-900' => $variant === 'cta',
    'bg-white text-brand-800 shadow-sm hover:bg-brand-50' => $variant === 'light',
    'border border-white/40 text-white hover:bg-white/10' => $variant === 'outline-light',
]) }}>{{ $slot }}</a>
