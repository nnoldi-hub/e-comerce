<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Ledger append-only: rândurile se inserează, nu se modifică și nu se șterg.
 */
class InventoryMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'product_variant_id', 'type', 'quantity', 'balance_after',
        'reference_type', 'reference_id', 'reason', 'created_by',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Mișcările de stoc sunt imuabile.');
        });

        static::deleting(function () {
            throw new LogicException('Mișcările de stoc nu se șterg.');
        });
    }

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'integer',
            'balance_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
