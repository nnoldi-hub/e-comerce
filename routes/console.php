<?php

use App\Domain\Checkout\Jobs\ReleaseExpiredReservationsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('payments:reconcile-refunds')->hourly()->withoutOverlapping();

// Plasă de siguranță pentru alertele de stoc (observer-ul le prinde în timp real).
Schedule::command('inventory:check-low-stock')->hourly()->withoutOverlapping();

// Jurnalul de audit păstrează `delete_records_older_than_days` zile (config/activitylog.php).
Schedule::command('activitylog:clean')->daily();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Eliberează rezervările de stoc expirate și anulează comenzile neplătite aferente.
Schedule::job(new ReleaseExpiredReservationsJob)->everyMinute();
