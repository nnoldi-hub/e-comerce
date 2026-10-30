<?php

namespace App\Policies;

use App\Models\Attribute;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AttributePolicy
{
    private const PERMISSION = 'attributes.manage';

    public function viewAny(User $user): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function view(User $user, Attribute $record): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function create(User $user): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function update(User $user, Attribute $record): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function delete(User $user, Attribute $record): bool
    {
        return $user->can(self::PERMISSION) && ! $this->isInUse($record);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(self::PERMISSION);
    }

    /** Ce este în uz nu se șterge (dezactivează-l în schimb). */
    private function isInUse(Attribute $record): bool
    {
        return DB::table('product_attribute_values')
            ->whereIn('attribute_value_id', $record->values()->select('id'))
            ->exists();
    }
}
