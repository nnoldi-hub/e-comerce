<?php

namespace App\Domain\Checkout\Jobs;

use App\Domain\Checkout\Actions\ReleaseExpiredReservations;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReleaseExpiredReservationsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 120;

    public function handle(ReleaseExpiredReservations $action): void
    {
        $action->execute();
    }
}
