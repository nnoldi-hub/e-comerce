<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property string $carrier
 * @property string|null $tracking_number
 * @property string|null $tracking_url
 * @property ShipmentStatus $status
 * @property Carbon|null $shipped_at
 * @property Carbon|null $delivered_at
 * @property int|null $created_by
 */
class Shipment extends Model
{
    protected $fillable = [
        'order_id', 'carrier', 'tracking_number', 'tracking_url', 'status',
        'shipped_at', 'delivered_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
