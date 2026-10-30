<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\ListRoles;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use UnitEnum;

/**
 * Doar citire: ce permisiuni are fiecare rol și câți utilizatori îl au.
 * Rolurile se definesc în `RolesAndPermissionsSeeder`, versionat și testat;
 * editarea lor din panou ar ocoli revizuirea.
 */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $slug = 'roles';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'rol';

    protected static ?string $pluralModelLabel = 'roluri';

    public static function canViewAny(): bool
    {
        return Gate::allows('roles.manage');
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
        $query = Role::query()->with('permissions')->withCount('users');

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Rol')->sortable()->weight('bold'),
                TextColumn::make('users_count')->label('Utilizatori'),
                TextColumn::make('permissions.name')->label('Permisiuni')->badge()
                    ->limitList(6)->expandableLimitedList(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
        ];
    }
}
