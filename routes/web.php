<?php

use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\AddressController;
use App\Http\Controllers\Account\OrderController as AccountOrderController;
use App\Http\Controllers\Cart\CartController;
use App\Http\Controllers\Catalog\CatalogController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Checkout\CheckoutController;
use App\Http\Controllers\Checkout\PayOrderController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\EnsureActiveCustomer;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// SEO: generate dinamic (fișierul static public/robots.txt trebuie șters, altfel are prioritate).
Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/sitemap.xml', SitemapController::class)->middleware('throttle:30,1')->name('sitemap');

// Fortify redirecționează aici după autentificare (config/fortify.php `home`).
Route::redirect('/home', '/cont');

// Catalog: lista (cu căutare/filtre), categorie după cale materializată, produs după slug.
Route::get('/produse', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/categorie/{path}', [CatalogController::class, 'category'])->where('path', '[a-z0-9\-\/]+')->name('catalog.category');
Route::get('/produs/{product:slug}', ProductController::class)->name('catalog.product');

// Contul clientului. Doar clienți activi; personalul folosește /admin.
Route::middleware(['auth', EnsureActiveCustomer::class])->prefix('cont')->name('account.')->group(function () {
    Route::get('/', [AccountController::class, 'index'])->name('index');
    Route::get('/comenzi', [AccountOrderController::class, 'index'])->name('orders.index');
    Route::get('/comenzi/{number}', [AccountOrderController::class, 'show'])->where('number', '[A-Za-z0-9\-]+')->name('orders.show');
    Route::get('/profil', [AccountController::class, 'profile'])->name('profile');
    Route::get('/parola', [AccountController::class, 'password'])->name('password');
    Route::get('/adrese', [AddressController::class, 'index'])->name('addresses.index');
    Route::get('/adrese/noua', [AddressController::class, 'create'])->name('addresses.create');
    Route::post('/adrese', [AddressController::class, 'store'])->name('addresses.store');
    Route::get('/adrese/{address}/editeaza', [AddressController::class, 'edit'])->whereNumber('address')->name('addresses.edit');
    Route::put('/adrese/{address}', [AddressController::class, 'update'])->whereNumber('address')->name('addresses.update');
    Route::delete('/adrese/{address}', [AddressController::class, 'destroy'])->whereNumber('address')->name('addresses.destroy');
    Route::post('/adrese/{address}/implicita', [AddressController::class, 'makeDefault'])->whereNumber('address')->name('addresses.default');
    // Codurile de recuperare și cheia 2FA se afișează doar după reconfirmarea parolei.
    Route::get('/securitate', [AccountController::class, 'security'])->middleware('password.confirm')->name('security');
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
