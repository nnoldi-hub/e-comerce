<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Activities\ActivityResource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class RecentActivityTable extends TableWidget
{
    protected static ?int $sort = 50;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return ActivityResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Activitate recentă')
            ->query(fn (): Builder => Activity::query()->with('causer')->latest('id')->limit(8))
            ->columns([
                TextColumn::make('created_at')->label('Când')->dateTime('d.m.Y H:i'),
                TextColumn::make('description')->label('Acțiune'),
                TextColumn::make('subject')->label('Obiect')
                    ->state(fn (Activity $record): string => ActivityResource::subjectText($record)),
                TextColumn::make('causer.name')->label('Utilizator')->placeholder('Sistem'),
            ])
            ->defaultSort('id', 'desc')
            ->paginated(false);
    }
}
