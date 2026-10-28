<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'sku', 'barcode', 'price', 'compare_at_price', 'cost_price',
        'weight_g', 'stock_on_hand', 'stock_reserved', 'low_stock_threshold',
        'track_inventory', 'allow_backorder', 'is_default', 'position',
    ];

    protected $casts = [
        'price' => 'integer',
        'compare_at_price' => 'integer',
        'cost_price' => 'integer',
        'weight_g' => 'integer',
        'stock_on_hand' => 'integer',
        'stock_reserved' => 'integer',
        'low_stock_threshold' => 'integer',
        'track_inventory' => 'boolean',
        'allow_backorder' => 'boolean',
        'is_default' => 'boolean',
        'position' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getAvailableStockAttribute(): int
    {
        return max(0, $this->stock_on_hand - $this->stock_reserved);
    }
}
