<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;
use LogicException;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    private function customer(): User
    {
        $record = $this->getRecord();

        if (! $record instanceof User) {
            throw new LogicException('Înregistrarea paginii nu este un utilizator.');
        }

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleActive')
                ->label(fn (): string => $this->customer()->is_active ? 'Dezactivează contul' : 'Activează contul')
                ->icon(fn (): string => $this->customer()->is_active ? 'heroicon-o-no-symbol' : 'heroicon-o-check-circle')
                ->color(fn (): string => $this->customer()->is_active ? 'danger' : 'success')
                ->requiresConfirmation()
                ->modalDescription('Un cont dezactivat nu se mai poate conecta în magazin. Comenzile și istoricul rămân neschimbate.')
                ->visible(fn (): bool => Gate::allows('customers.update'))
                ->action(function (): void {
                    Gate::authorize('customers.update');

                    $customer = $this->customer();
                    $customer->update(['is_active' => ! $customer->is_active]);

                    Notification::make()
                        ->title($customer->is_active ? 'Cont activat' : 'Cont dezactivat')
                        ->success()
                        ->send();
                }),
        ];
    }
}
