<?php

use App\Http\Controllers\Cart\CartController;
use App\Http\Controllers\Checkout\CheckoutController;
use App\Http\Controllers\Checkout\PayOrderController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Monitorizare (cu token); `/up` rămâne verificarea simplă a framework-ului.
Route::get('/health', HealthController::class)->middleware('throttle:30,1')->name('health');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');

// Limitele sunt în config/security.php (limitatoarele `cart` și `checkout`).
Route::middleware('throttle:cart')->group(function () {
    Route::post('/cart/add/{variant}', [CartController::class, 'add'])->name('cart.add');
    Route::patch('/cart/item/{item}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/item/{item}', [CartController::class, 'remove'])->name('cart.remove');
});

Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout.store');
Route::get('/checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');

// Link semnat (24 h) generat pe pagina de succes; permite reluarea plății.
Route::post('/checkout/pay/{order:number}', PayOrderController::class)
    ->middleware(['signed', 'throttle:10,1'])
    ->name('checkout.pay');

// Webhook-urile providerilor trăiesc în routes/webhooks.php (fără sesiune/CSRF).
