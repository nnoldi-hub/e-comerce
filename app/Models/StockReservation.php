<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReservation extends Model
{
    protected $fillable = [
        'product_variant_id', 'cart_id', 'order_id', 'quantity', 'expires_at', 'released_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'expires_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Rezervări neeliberate și neexpirate. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('released_at')->where('expires_at', '>', now());
    }

    /** Rezervări expirate care nu au fost încă eliberate. */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNull('released_at')->where('expires_at', '<=', now());
    }
}
