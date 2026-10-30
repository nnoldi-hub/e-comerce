@props(['status'])
@php
    $classes = match ($status->value) {
        'paid', 'completed', 'delivered' => 'bg-green-50 text-green-800',
        'shipped', 'processing' => 'bg-blue-50 text-blue-800',
        'cancelled', 'returned', 'refunded' => 'bg-zinc-100 text-zinc-700',
        default => 'bg-amber-50 text-amber-800',
    };
@endphp
<span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium {{ $classes }}">{{ $status->label() }}</span>
