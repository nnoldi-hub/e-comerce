@props(['path' => null, 'name' => ''])
{{-- Imaginea unei categorii; fără imagine, un pătrat cu inițiala (numele e oricum în textul linkului, deci alt="" ). --}}
@if ($path)
    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($path) }}" alt="" loading="lazy" decoding="async"
         {{ $attributes->class(['bg-white object-contain p-2']) }}>
@else
    <span {{ $attributes->class(['flex items-center justify-center bg-gradient-to-br from-brand-50 to-zinc-100 text-3xl font-bold text-brand-700']) }} aria-hidden="true">{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</span>
@endif
