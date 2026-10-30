<?php

namespace App\Policies;

use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use App\Models\User;

class ProductVariantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }

    public function view(User $user, ProductVariant $variant): bool
    {
        return $user->can('products.view');
    }

    public function create(User $user): bool
    {
        return $user->can('products.create');
    }

    public function update(User $user, ProductVariant $variant): bool
    {
        return $user->can('products.update');
    }

    /**
     * O variantă cu istoric (mișcări de stoc sau rezervări) nu se șterge:
     * ledger-ul și comenzile trebuie să rămână explicabile.
     */
    public function delete(User $user, ProductVariant $variant): bool
    {
        return $user->can('products.delete')
            && ! InventoryMovement::query()->where('product_variant_id', $variant->id)->exists()
            && ! StockReservation::query()->where('product_variant_id', $variant->id)->exists();
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('products.delete');
    }
}
