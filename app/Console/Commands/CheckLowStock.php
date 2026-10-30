<?php

namespace App\Console\Commands;

use App\Domain\Inventory\LowStockMonitor;
use Illuminate\Console\Command;

class CheckLowStock extends Command
{
    protected $signature = 'inventory:check-low-stock';

    protected $description = 'Reevaluează alertele de stoc redus (deschide, escaladează sau rezolvă)';

    public function handle(LowStockMonitor $monitor): int
    {
        $count = $monitor->sweep();

        $this->info("Variante evaluate: {$count}.");

        return self::SUCCESS;
    }
}
