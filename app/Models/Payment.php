<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Support\Casts\MoneyCast;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property string $provider
 * @property string|null $provider_reference
 * @property string|null $method
 * @property Money $amount
 * @property string $currency
 * @property PaymentStatus $status
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $paid_at
 * @property-read Order $order
 */
class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'provider', 'provider_reference', 'method',
        'amount', 'currency', 'status', 'payload', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'status' => PaymentStatus::class,
            'payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
