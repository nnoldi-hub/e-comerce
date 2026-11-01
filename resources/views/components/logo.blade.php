{{-- Logo NK-Shop: monogramă SVG + numele din config('app.name'). Se înlocuiește aici, într-un singur loc. --}}
@props(['light' => false])
<span {{ $attributes->class(['inline-flex items-center gap-2.5']) }}>
    <svg class="size-9 shrink-0" viewBox="0 0 40 40" fill="none" aria-hidden="true">
        <rect width="40" height="40" rx="10" @class(['fill-brand-600' => ! $light, 'fill-white' => $light])/>
        <path d="M10 28V12l9 16V12M23 12v16M31 12l-8 8m2.5-2.2L31 28" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" @class(['stroke-white' => ! $light, 'stroke-brand-700' => $light])/>
    </svg>
    <span class="text-xl font-bold tracking-tight">{{ config('app.name') }}</span>
</span>
