<?php

namespace App\Console\Commands;

use App\Domain\Checkout\Actions\ReleaseExpiredReservations;
use Illuminate\Console\Command;

class ReleaseExpiredReservationsCommand extends Command
{
    protected $signature = 'shop:release-reservations';

    protected $description = 'Eliberează rezervările de stoc expirate și anulează comenzile neplătite aferente';

    public function handle(ReleaseExpiredReservations $action): int
    {
        $count = $action->execute();

        $this->info("Rezervări eliberate: {$count}");

        return self::SUCCESS;
    }
}
