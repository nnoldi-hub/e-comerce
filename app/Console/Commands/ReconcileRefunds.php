<?php

namespace App\Console\Commands;

use App\Domain\Payments\Actions\ReconcilePendingRefundsAction;
use Illuminate\Console\Command;

class ReconcileRefunds extends Command
{
    protected $signature = 'payments:reconcile-refunds {--minutes=30 : Reconciliază rambursările `pending` mai vechi de atât}';

    protected $description = 'Închide rambursările rămase în așteptare, întrebând procesatorul';

    public function handle(ReconcilePendingRefundsAction $reconcile): int
    {
        $changed = $reconcile->execute(max(1, (int) $this->option('minutes')));

        $this->info("Rambursări actualizate: {$changed}.");

        return self::SUCCESS;
    }
}
