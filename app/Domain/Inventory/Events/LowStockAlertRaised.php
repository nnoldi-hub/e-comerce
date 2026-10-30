<?php

namespace App\Domain\Inventory\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class LowStockAlertRaised implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $alertId,
        public readonly bool $outOfStock,
    ) {}
}
