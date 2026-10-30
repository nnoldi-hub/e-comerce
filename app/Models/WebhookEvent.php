<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jurnal al webhook-urilor primite. `status`: pending (în curs / de reîncercat),
 * processed, failed (abandonat, vezi `error`).
 */
class WebhookEvent extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'provider', 'event_id', 'type', 'payload', 'status', 'attempts', 'error', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'processed_at' => 'datetime',
        ];
    }
}
