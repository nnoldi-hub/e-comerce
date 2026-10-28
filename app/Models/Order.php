<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Support\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'number', 'user_id', 'email', 'status', 'payment_status', 'fulfillment_status',
        'subtotal', 'discount_total', 'shipping_total', 'tax_total', 'grand_total', 'currency',
        'billing_address', 'shipping_address', 'coupon_code', 'idempotency_key',
        'placed_at', 'customer_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'subtotal' => MoneyCast::class,
            'discount_total' => MoneyCast::class,
            'shipping_total' => MoneyCast::class,
            'tax_total' => MoneyCast::class,
            'grand_total' => MoneyCast::class,
            'billing_address' => 'array',
            'shipping_address' => 'array',
            'placed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
