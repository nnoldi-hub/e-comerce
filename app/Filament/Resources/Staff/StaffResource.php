<?php

namespace App\Filament\Resources\Staff;

use App\Domain\Users\Exceptions\InvalidStaffChangeException;
use App\Domain\Users\StaffAccounts;
use App\Filament\Resources\Staff\Pages\CreateStaff;
use App\Filament\Resources\Staff\Pages\EditStaff;
use App\Filament\Resources\Staff\Pages\ListStaff;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use LogicException;
use UnitEnum;

/**
 * Personalul panoului: utilizatorii care au cel puțin un rol diferit de „Client”.
 * Scrierile trec prin `StaffAccounts`; conturile nu se șterg, se dezactivează.
 */
class StaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'staff';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'membru al personalului';

    protected static ?string $pluralModelLabel = 'personal';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return Gate::allows('users.manage');
    }

    public static function canCreate(): bool
    {
        return Gate::allows('users.manage');
    }

    public static function canEdit(Model $record): bool
    {
        return Gate::allows('users.manage');
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
            ->with('roles')
            ->whereHas('roles', fn (Builder $roles) => $roles->where('name', '!=', 'Client'));

        return $query;
    }

    public static function actor(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new LogicException('Nu există un utilizator autentificat.');
        }

        return $user;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Cont')->schema([
                TextInput::make('name')->label('Nume')->required()->maxLength(255),
                TextInput::make('email')->label('Email')->email()->required()->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('phone')->label('Telefon')->tel()->maxLength(30),
                TextInput::make('password')->label('Parolă')
                    ->password()->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->minLength(12)
                    ->dehydrated(fn ($state): bool => filled($state))
                    ->helperText('Cel puțin 12 caractere. La editare, lasă gol ca să nu o schimbi.'),
            ])->columns(2),
            Section::make('Acces')->schema([
                Select::make('role_names')->label('Roluri')
                    ->options(fn (): array => array_combine(StaffAccounts::assignableRoles(), StaffAccounts::assignableRoles()))
                    ->multiple()
                    ->required()
                    ->preload()
                    ->helperText('Rolurile decid ce vede și ce poate face în panou.'),
                Toggle::make('is_active')->label('Cont activ')->default(true)
                    ->helperText('Un cont dezactivat nu se mai poate conecta. Conturile nu se șterg.'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nume')->searchable()->sortable(),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('roles.name')->label('Roluri')->badge(),
                IconColumn::make('mfa')->label('2FA')->boolean()
                    ->state(fn (User $record): bool => filled($record->app_authentication_secret)),
                IconColumn::make('is_active')->label('Activ')->boolean(),
                TextColumn::make('last_login_at')->label('Ultima conectare')->dateTime('d.m.Y H:i')->placeholder('—')->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')->label('Rol')
                    ->options(fn (): array => array_combine(StaffAccounts::assignableRoles(), StaffAccounts::assignableRoles()))
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', $data['value']))
                        : $query),
                SelectFilter::make('is_active')->label('Cont')
                    ->options(['1' => 'Activ', '0' => 'Dezactivat']),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                EditAction::make(),
                Action::make('resetMfa')
                    ->label('Resetează 2FA')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Secretul curent se șterge. La următoarea conectare, utilizatorul trebuie să configureze din nou aplicația de autentificare.')
                    ->visible(fn (User $record): bool => Gate::allows('users.manage') && filled($record->app_authentication_secret))
                    ->action(function (User $record): void {
                        try {
                            app(StaffAccounts::class)->resetMfa(self::actor(), $record);
                        } catch (InvalidStaffChangeException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('2FA resetat')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaff::route('/'),
            'create' => CreateStaff::route('/create'),
            'edit' => EditStaff::route('/{record}/edit'),
        ];
    }
}
