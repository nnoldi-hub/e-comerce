@props(['variant' => 'primary'])
<button {{ $attributes->merge(['type' => 'submit'])->class([
    'inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-medium transition disabled:opacity-50',
    'bg-brand-600 text-white hover:bg-brand-700' => $variant === 'primary',
    'border border-zinc-300 bg-white hover:bg-zinc-50' => $variant === 'secondary',
]) }}>{{ $slot }}</button>
