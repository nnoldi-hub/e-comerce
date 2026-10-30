<?php

namespace App\Enums;

enum RefundStatus: string
{
    case PENDING = 'pending';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';

    /** O rambursare în curs sau reușită consumă din suma rambursabilă. */
    public function countsAgainstPayment(): bool
    {
        return $this !== self::FAILED;
    }
}
