<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class CustomersOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 12;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Clienți';

    public static function canView(): bool
    {
        return Gate::allows('customers.view');
    }

    /**
     * Clienți = utilizatori fără niciun rol de personal (la fel ca în CustomerResource).
     *
     * @return Builder<User>
     */
    private function customers(): Builder
    {
        return User::query()->whereDoesntHave('roles', fn ($q) => $q->where('name', '!=', 'Client'));
    }

    protected function getStats(): array
    {
        $now = CarbonImmutable::now();

        $total = $this->customers()->count();
        $week = $this->customers()->where('created_at', '>=', $now->subDays(6)->startOfDay())->count();
        $month = $this->customers()->where('created_at', '>=', $now->subDays(29)->startOfDay())->count();

        return [
            Stat::make('Clienți', (string) $total)->description('conturi create în magazin'),
            Stat::make('Clienți noi, 7 zile', (string) $week)->color($week > 0 ? 'success' : 'gray'),
            Stat::make('Clienți noi, 30 de zile', (string) $month)->color($month > 0 ? 'success' : 'gray'),
        ];
    }
}
