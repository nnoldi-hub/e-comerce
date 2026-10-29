<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

use App\Http\Controllers\Cart\CartController;

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/add/{variant}', [CartController::class, 'add'])->name('cart.add');
Route::patch('/cart/item/{item}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/item/{item}', [CartController::class, 'remove'])->name('cart.remove');

use App\Http\Controllers\Checkout\CheckoutController;

Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');

use App\Http\Controllers\Payments\PaymentWebhookController;

Route::post('/webhooks/payments/{gateway}', PaymentWebhookController::class)->name('webhooks.payments');
