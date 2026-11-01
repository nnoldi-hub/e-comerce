@props(['rating' => 0, 'count' => null, 'size' => 'size-4'])
@php
    $value = max(0, min(5, (float) $rating));
    $star = 'M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z';
@endphp
<span {{ $attributes->class(['inline-flex items-center gap-1']) }}>
    <span class="inline-flex" role="img" aria-label="Notă {{ number_format($value, 1, ',', '') }} din 5">
        @for ($i = 1; $i <= 5; $i++)
            <svg viewBox="0 0 24 24" class="{{ $size }} {{ $value >= $i - 0.25 ? 'text-amber-400' : 'text-zinc-300' }}" fill="currentColor" aria-hidden="true"><path d="{{ $star }}"/></svg>
        @endfor
    </span>
    @if ($count !== null)
        <span class="text-xs text-zinc-500">{{ number_format($value, 1, ',', '') }} ({{ $count }})</span>
    @endif
</span>
