<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Domain\Users\Exceptions\InvalidStaffChangeException;
use App\Domain\Users\StaffAccounts;
use App\Filament\Resources\Staff\StaffResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateStaff extends CreateRecord
{
    protected static string $resource = StaffResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $roles = array_map('strval', (array) ($data['role_names'] ?? []));
        unset($data['role_names']);

        try {
            return app(StaffAccounts::class)->create(StaffResource::actor(), $data, array_values($roles));
        } catch (InvalidStaffChangeException $e) {
            throw ValidationException::withMessages(['data.'.$e->field => $e->getMessage()]);
        }
    }
}
