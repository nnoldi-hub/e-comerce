@php
    // Doar resursele care există, au pagină „create" și la care utilizatorul are voie.
    $candidates = [
        'Produs' => \App\Filament\Resources\Products\ProductResource::class,
        'Categorie' => 'App\Filament\Resources\Categories\CategoryResource',
        'Brand' => 'App\Filament\Resources\Brands\BrandResource',
        'Cupon' => 'App\Filament\Resources\Coupons\CouponResource',
    ];

    $links = [];

    foreach ($candidates as $label => $resource) {
        if (class_exists($resource) && array_key_exists('create', $resource::getPages()) && $resource::canCreate()) {
            $links[$label] = $resource::getUrl('create');
        }
    }
@endphp
@if ($links !== [])
    <x-filament::dropdown placement="bottom-end">
        <x-slot name="trigger">
            <x-filament::button size="sm" icon="heroicon-m-plus">Adaugă</x-filament::button>
        </x-slot>
        <x-filament::dropdown.list>
            @foreach ($links as $label => $url)
                <x-filament::dropdown.list.item :href="$url" tag="a">{{ $label }}</x-filament::dropdown.list.item>
            @endforeach
        </x-filament::dropdown.list>
    </x-filament::dropdown>
@endif
