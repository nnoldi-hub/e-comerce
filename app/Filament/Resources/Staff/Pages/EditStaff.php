<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Domain\Users\Exceptions\InvalidStaffChangeException;
use App\Domain\Users\StaffAccounts;
use App\Filament\Resources\Staff\StaffResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditStaff extends EditRecord
{
    protected static string $resource = StaffResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        $data['role_names'] = $record instanceof User ? $record->roles()->orderBy('name')->pluck('name')->all() : [];

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $roles = array_map('strval', (array) ($data['role_names'] ?? []));
        unset($data['role_names']);

        if (! $record instanceof User) {
            return $record;
        }

        try {
            return app(StaffAccounts::class)->update(StaffResource::actor(), $record, $data, array_values($roles));
        } catch (InvalidStaffChangeException $e) {
            throw ValidationException::withMessages(['data.'.$e->field => $e->getMessage()]);
        }
    }
}
