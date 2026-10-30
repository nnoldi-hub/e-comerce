<?php

namespace App\Models;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Support\Casts\MoneyCast;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $number
 * @property int|null $user_id
 * @property string $email
 * @property OrderStatus $status
 * @property PaymentStatus $payment_status
 * @property string|null $payment_method
 * @property FulfillmentStatus $fulfillment_status
 * @property Money $subtotal
 * @property Money $discount_total
 * @property Money $shipping_total
 * @property Money $tax_total
 * @property Money $grand_total
 * @property string $currency
 * @property array<string, mixed>|null $billing_address
 * @property array<string, mixed>|null $shipping_address
 * @property string|null $coupon_code
 * @property string|null $idempotency_key
 * @property Carbon|null $placed_at
 * @property string|null $customer_note
 * @property-read Collection<int, OrderItem> $items
 * @property-read Collection<int, Payment> $payments
 * @property-read Collection<int, OrderStatusHistory> $statusHistories
 */
class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'number', 'user_id', 'email', 'status', 'payment_status', 'payment_method', 'fulfillment_status',
        'subtotal', 'discount_total', 'shipping_total', 'tax_total', 'grand_total', 'currency',
        'billing_address', 'shipping_address', 'coupon_code', 'idempotency_key',
        'placed_at', 'customer_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'fulfillment_status' => FulfillmentStatus::class,
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

    /** @return HasMany<OrderStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    public function latestPayment(): ?Payment
    {
        return Payment::query()->where('order_id', $this->id)->latest('id')->first();
    }

    public function isPaid(): bool
    {
        return $this->payment_status === PaymentStatus::PAID;
    }
}
