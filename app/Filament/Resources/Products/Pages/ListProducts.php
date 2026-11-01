<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductImportActions;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...ProductImportActions::make(),
            CreateAction::make(),
        ];
    }

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('Toate')->badge(Product::query()->count())];

        foreach (ProductResource::STATUSES as $status => $label) {
            $tabs[$status] = Tab::make($label)
                ->badge(Product::query()->where('status', $status)->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status));
        }

        return $tabs;
    }
}
