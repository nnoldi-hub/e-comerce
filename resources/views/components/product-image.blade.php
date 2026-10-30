@props(['product', 'class' => ''])
@php
    $media = $product->getFirstMedia('thumbnail') ?? $product->getFirstMedia('gallery');
    $url = $media ? ($media->hasGeneratedConversion('card') ? $media->getUrl('card') : $media->getUrl()) : null;
@endphp
@if ($url)
    <img src="{{ $url }}" alt="{{ $product->name }}" loading="lazy" class="{{ $class }} object-cover">
@else
    <div class="{{ $class }} flex items-center justify-center bg-zinc-100 text-xs text-zinc-400" role="img" aria-label="Fără imagine">Fără imagine</div>
@endif
