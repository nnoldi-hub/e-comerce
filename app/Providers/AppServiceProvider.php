<?php

namespace App\Providers;

use App\Domain\Payments\Gateways\StripeGateway;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StripeGateway::class, fn () => new StripeGateway(
            (string) config('shop.payments.gateways.stripe.secret', ''),
            (string) config('shop.payments.gateways.stripe.webhook_secret', ''),
            (int) config('shop.payments.gateways.stripe.tolerance', 300),
        ));
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! app()->isProduction());
        Model::preventLazyLoading(! app()->isProduction());

        Relation::enforceMorphMap([
            'user' => User::class,
            'product' => Product::class,
            'product_variant' => ProductVariant::class,
            'category' => Category::class,
            'brand' => Brand::class,
            'cart' => Cart::class,
            'order' => Order::class,
        ]);

        Gate::before(function ($user, $ability) {
            if ($user->hasRole('Super Admin')) {
                return true;
            }
        });
    }
}
