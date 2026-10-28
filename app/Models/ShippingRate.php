<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRate extends Model
{
    protected $fillable = ['shipping_method_id', 'min_subtotal', 'max_subtotal', 'price'];

    protected function casts(): array
    {
        return [
            'min_subtotal' => 'integer',
            'max_subtotal' => 'integer',
            'price' => 'integer',
        ];
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class, 'shipping_method_id');
    }
}
