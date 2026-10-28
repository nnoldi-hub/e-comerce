<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Support\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
