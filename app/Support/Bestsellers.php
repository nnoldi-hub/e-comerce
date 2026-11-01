<?php

namespace App\Support;

use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cele mai vândute produse din ultimele 90 de zile (comenzi plătite sau în curs de livrare).
 * Lista se ține o oră în cache; pe cerere se citește o singură dată (`Scoped`).
 */
#[Scoped]
final class Bestsellers
{
    public const LIMIT = 8;

    private const STATUSES = ['paid', 'processing', 'shipped', 'delivered', 'completed'];

    /** @var list<int>|null */
    private ?array $ids = null;

    /** @return list<int> ID-uri de produs, cele mai vândute primele. */
    public function ids(): array
    {
        return $this->ids ??= Cache::remember('storefront.bestsellers', 3600, fn (): array => $this->query());
    }

    public function contains(int $productId): bool
    {
        return in_array($productId, $this->ids(), true);
    }

    /** @return list<int> */
    private function query(): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->whereNull('orders.deleted_at')
            ->whereIn('orders.status', self::STATUSES)
            ->where('orders.placed_at', '>=', now()->subDays(90))
            ->groupBy('product_variants.product_id')
            ->orderByRaw('SUM(order_items.quantity) DESC')
            ->limit(self::LIMIT)
            ->pluck('product_variants.product_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }
}
