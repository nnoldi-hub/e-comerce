<?php

namespace App\Policies;

use App\Models\AttributeValue;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AttributeValuePolicy
{
    private const PERMISSION = 'attributes.manage';

    public function viewAny(User $user): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function view(User $user, AttributeValue $record): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function create(User $user): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function update(User $user, AttributeValue $record): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function delete(User $user, AttributeValue $record): bool
    {
        return $user->can(self::PERMISSION)
            && ! DB::table('product_attribute_values')->where('attribute_value_id', $record->id)->exists();
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(self::PERMISSION);
    }
}
