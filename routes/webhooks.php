<?php

use App\Http\Controllers\CspReportController;
use App\Http\Controllers\Payments\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Încărcat din bootstrap/app.php cu prefixul `webhooks` și limitatorul
| `webhooks`. Nu trece prin grupul `web`: fără sesiune, cookie-uri sau CSRF.
*/
Route::post('/payments/{gateway}', PaymentWebhookController::class)->name('webhooks.payments');

// Rapoarte CSP trimise de browsere (fără CSRF); limită proprie, mai strânsă.
Route::post('/csp-report', CspReportController::class)->middleware('throttle:30,1')->name('webhooks.csp');
