<?php

namespace App\Filament\Resources\Customers;

use App\Domain\Reporting\SalesReport;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Clienții magazinului: utilizatorii fără niciun rol de personal. Cei fără rol
 * deloc (înregistrați din vitrină) sunt tot clienți.
 */
class CustomerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'customers';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = 'Clienți';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'client';

    protected static ?string $pluralModelLabel = 'clienți';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return Gate::allows('customers.view');
    }

    public static function canView(Model $record): bool
    {
        return Gate::allows('customers.view');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Model> $query */
        $query = User::query()
            ->whereDoesntHave('roles', fn (Builder $query) => $query->where('name', '!=', 'Client'))
            ->addSelect([
                'orders_count' => Order::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('orders.user_id', 'users.id'),
                'orders_total' => Order::query()
                    ->selectRaw('COALESCE(SUM(grand_total), 0)')
                    ->whereColumn('orders.user_id', 'users.id')
                    ->whereIn('status', SalesReport::countedStatuses()),
            ]);

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nume')->searchable()->sortable(),
                TextColumn::make('email')->label('Email')->searchable()->copyable(),
                TextColumn::make('phone')->label('Telefon')->placeholder('—')->toggleable(),
                TextColumn::make('orders_count')->label('Comenzi')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('orders_count', $direction)),
                TextColumn::make('orders_total')->label('Cheltuit')
                    ->formatStateUsing(fn ($state): string => OrderResource::money((int) $state))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('orders_total', $direction)),
                IconColumn::make('is_active')->label('Activ')->boolean(),
                TextColumn::make('created_at')->label('Înregistrat')->dateTime('d.m.Y')->sortable()->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Cont activ'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Client')->schema([
                TextEntry::make('name')->label('Nume'),
                TextEntry::make('email')->label('Email')->copyable(),
                TextEntry::make('phone')->label('Telefon')->placeholder('—'),
                TextEntry::make('is_active')->label('Cont')->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'Activ' : 'Dezactivat')
                    ->color(fn ($state): string => $state ? 'success' : 'danger'),
                TextEntry::make('email_verified_at')->label('Email verificat la')->dateTime('d.m.Y H:i')->placeholder('neverificat'),
                TextEntry::make('created_at')->label('Înregistrat la')->dateTime('d.m.Y H:i'),
                TextEntry::make('last_login_at')->label('Ultima conectare')->dateTime('d.m.Y H:i')->placeholder('—'),
            ])->columns(3),
            Section::make('Comenzi')->schema([
                TextEntry::make('orders_count')->label('Număr de comenzi'),
                TextEntry::make('orders_total')->label('Total cheltuit (comenzi confirmate)')
                    ->formatStateUsing(fn ($state): string => OrderResource::money((int) $state)),
                RepeatableEntry::make('recent_orders')
                    ->label('Ultimele comenzi')
                    ->state(fn (User $record): array => self::recentOrders($record))
                    ->schema([
                        TextEntry::make('number')->label('Număr'),
                        TextEntry::make('status')->label('Status'),
                        TextEntry::make('total')->label('Total'),
                        TextEntry::make('placed_at')->label('Plasată la'),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
            ])->columns(2)->visible(fn (): bool => Gate::allows('viewAny', Order::class)),
        ]);
    }

    /**
     * @return list<array{number: string, status: string, total: string, placed_at: string}>
     */
    public static function recentOrders(User $customer): array
    {
        return Order::query()
            ->where('user_id', $customer->getKey())
            ->orderByDesc('placed_at')
            ->limit(20)
            ->get()
            ->map(fn (Order $order): array => [
                'number' => (string) $order->number,
                'status' => OrderResource::statusLabel($order->status),
                'total' => OrderResource::money($order->grand_total),
                'placed_at' => $order->placed_at?->format('d.m.Y H:i') ?? '—',
            ])
            ->values()
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'view' => ViewCustomer::route('/{record}'),
        ];
    }
}
