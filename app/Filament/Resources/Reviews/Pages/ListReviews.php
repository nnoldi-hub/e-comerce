<?php

namespace App\Filament\Resources\Reviews\Pages;

use App\Filament\Resources\Reviews\ReviewResource;
use App\Models\Review;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListReviews extends ListRecords
{
    protected static string $resource = ReviewResource::class;

    public function getDefaultActiveTab(): string|int|null
    {
        return Review::PENDING;
    }

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        $tabs = [];

        foreach (Review::STATUSES as $status => $label) {
            $tabs[$status] = Tab::make($label)
                ->badge(Review::query()->where('status', $status)->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status));
        }

        $tabs['all'] = Tab::make('Toate');

        return $tabs;
    }
}
