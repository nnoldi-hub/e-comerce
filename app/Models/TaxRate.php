<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    protected $fillable = ['name', 'rate_bps', 'country', 'is_inclusive', 'is_default', 'is_active'];

    protected function casts(): array
    {
        return [
            'rate_bps' => 'integer',
            'is_inclusive' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
