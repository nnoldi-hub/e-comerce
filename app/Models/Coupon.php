<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'type', 'value', 'min_subtotal', 'max_uses', 'max_uses_per_user', 'starts_at', 'ends_at', 'is_active'];

    protected $casts = [
        'value' => 'integer',
        'min_subtotal' => 'integer',
        'max_uses' => 'integer',
        'max_uses_per_user' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }
}
