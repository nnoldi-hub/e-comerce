{{-- Breadcrumb vizibil + date structurate BreadcrumbList (Google). $items: [[etichetă, url|null], …]; ultimul = pagina curentă. --}}
@props(['items'])
@php
    $items = array_values($items);
    $structured = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => array_map(
            fn (array $item, int $i) => array_filter([
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item[0],
                'item' => $i === count($items) - 1 ? null : $item[1],
            ]),
            $items,
            array_keys($items),
        ),
    ];
@endphp
<nav aria-label="Breadcrumb" {{ $attributes->class(['flex flex-wrap items-center gap-1.5 text-sm text-zinc-500']) }}>
    @foreach ($items as [$label, $url])
        @if (! $loop->first)
            <x-ui-icon name="chevron" class="size-3.5 text-zinc-300" />
        @endif
        @if ($url && ! $loop->last)
            <a href="{{ $url }}" class="hover:text-zinc-900 hover:underline">{{ $label }}</a>
        @else
            <span aria-current="page" class="text-zinc-900">{{ $label }}</span>
        @endif
    @endforeach
</nav>
<script type="application/ld+json">{!! json_encode($structured, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
