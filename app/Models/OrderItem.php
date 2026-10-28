<?php

namespace App\Models;

use App\Support\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'product_variant_id', 'name', 'sku',
        'unit_price', 'tax_rate_bps', 'quantity', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => MoneyCast::class,
            'line_total' => MoneyCast::class,
            'tax_rate_bps' => 'integer',
            'quantity' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
