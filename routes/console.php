<?php

use App\Domain\Checkout\Jobs\ReleaseExpiredReservationsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('payments:reconcile-refunds')->hourly()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Eliberează rezervările de stoc expirate și anulează comenzile neplătite aferente.
Schedule::job(new ReleaseExpiredReservationsJob)->everyMinute();
