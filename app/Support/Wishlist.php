<?php

namespace App\Support;

use App\Models\WishlistItem;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Facades\Auth;

/**
 * Produsele favorite ale clientului curent, citite o singură dată pe cerere
 * (cardurile din listă întreabă „e favorit?” de zeci de ori).
 */
#[Scoped]
final class Wishlist
{
    /** @var array<int, true>|null */
    private ?array $ids = null;

    public function has(int $productId): bool
    {
        return isset($this->ids()[$productId]);
    }

    /** @return array<int, true> */
    private function ids(): array
    {
        if ($this->ids !== null) {
            return $this->ids;
        }

        $userId = Auth::id();
        $this->ids = [];

        if ($userId !== null) {
            foreach (WishlistItem::query()->where('user_id', $userId)->pluck('product_id') as $id) {
                $this->ids[(int) $id] = true;
            }
        }

        return $this->ids;
    }
}
