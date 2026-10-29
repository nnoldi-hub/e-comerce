<?php

namespace App\Models;

use App\Enums\RefundStatus;
use App\Support\Casts\MoneyCast;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $payment_id
 * @property int $order_id
 * @property Money $amount
 * @property string $currency
 * @property string|null $reason
 * @property RefundStatus $status
 * @property string|null $provider_reference
 * @property string|null $failure_reason
 * @property int|null $created_by
 * @property Carbon|null $processed_at
 */
class Refund extends Model
{
    protected $fillable = [
        'payment_id', 'order_id', 'amount', 'currency', 'reason', 'status',
        'provider_reference', 'failure_reason', 'created_by', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'status' => RefundStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
