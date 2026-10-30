<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * O alertă „stoc redus” rămâne deschisă cât timp varianta este sub prag; se
 * rezolvă când stocul urcă peste prag. Cât e deschisă nu se retrimite nimic,
 * în afară de o singură escaladare la stoc 0.
 *
 * @property int $id
 * @property int $product_variant_id
 * @property int $stock_on_hand
 * @property int $low_stock_threshold
 * @property Carbon|null $out_of_stock_notified_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 */
class LowStockAlert extends Model
{
    protected $fillable = [
        'product_variant_id', 'stock_on_hand', 'low_stock_threshold',
        'out_of_stock_notified_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'stock_on_hand' => 'integer',
            'low_stock_threshold' => 'integer',
            'out_of_stock_notified_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
