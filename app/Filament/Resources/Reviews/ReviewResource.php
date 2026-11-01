<?php

namespace App\Filament\Resources\Reviews;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Models\Review;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Moderarea recenzilor clienților. Cine poate vedea produsele poate modera recenziile lor.
 */
class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-star';

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 50;

    protected static ?string $modelLabel = 'recenzie';

    protected static ?string $pluralModelLabel = 'recenzii';

    public static function canViewAny(): bool
    {
        return ProductResource::canViewAny();
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
        return ProductResource::canViewAny();
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = Review::query()->where('status', Review::PENDING)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['product:id,name', 'user:id,name']))
            ->columns([
                TextColumn::make('created_at')->label('Data')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('product.name')->label('Produs')->limit(30)->searchable(),
                TextColumn::make('user.name')->label('Client')->searchable(),
                TextColumn::make('rating')->label('Notă')->formatStateUsing(fn ($state): string => $state.' ★')->sortable(),
                TextColumn::make('title')->label('Titlu')->limit(30)->placeholder('—'),
                TextColumn::make('body')->label('Recenzie')->limit(70)->tooltip(fn (Review $record): string => $record->body)->wrap(),
                IconColumn::make('verified_purchase')->label('Cumpărător')->boolean(),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state): string => Review::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        Review::APPROVED => 'success',
                        Review::REJECTED => 'danger',
                        default => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(Review::STATUSES),
                SelectFilter::make('rating')->label('Notă')->options([5 => '5 ★', 4 => '4 ★', 3 => '3 ★', 2 => '2 ★', 1 => '1 ★']),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('approve')->label('Aprobă')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (Review $record): bool => $record->status !== Review::APPROVED)
                    ->action(fn (Review $record) => $record->update(['status' => Review::APPROVED, 'moderated_at' => now()])),
                Action::make('reject')->label('Respinge')->icon('heroicon-o-x-mark')->color('danger')
                    ->visible(fn (Review $record): bool => $record->status !== Review::REJECTED)
                    ->action(fn (Review $record) => $record->update(['status' => Review::REJECTED, 'moderated_at' => now()])),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviews::route('/'),
        ];
    }
}
