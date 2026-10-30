<?php

use App\Http\Controllers\Payments\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Încărcat din bootstrap/app.php cu prefixul `webhooks` și limitatorul
| `webhooks`. Nu trece prin grupul `web`: fără sesiune, cookie-uri sau CSRF.
*/
Route::post('/payments/{gateway}', PaymentWebhookController::class)->name('webhooks.payments');
