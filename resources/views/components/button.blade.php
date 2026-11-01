@props(['variant' => 'primary'])
<button {{ $attributes->merge(['type' => 'submit'])->class([
    'inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
    'bg-brand-600 text-white shadow-sm hover:bg-brand-700 active:bg-brand-800' => $variant === 'primary',
    'border border-zinc-300 bg-white text-zinc-900 hover:bg-zinc-50' => $variant === 'secondary',
    'bg-accent-700 text-white shadow-sm hover:bg-accent-800 active:bg-accent-900' => $variant === 'cta',
]) }}>{{ $slot }}</button>
