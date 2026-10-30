<?php

namespace App\Providers;

use App\Domain\Payments\Gateways\StripeGateway;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Observers\AuditObserver;
use App\Observers\LowStockObserver;
use App\Policies\ActivityPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

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
            'attribute' => Attribute::class,
            'attribute_value' => AttributeValue::class,
            'coupon' => Coupon::class,
        ]);

        Gate::before(function ($user, $ability) {
            // Super Admin are acces total, dar nu ocoleste regulile de integritate la
            // stergere (ce este in uz nu se sterge): acelea traiesc in policy-uri.
            if ($ability !== 'delete' && $user->hasRole('Super Admin')) {
                return true;
            }
        });

        // Modelul Activity aparține pachetului: politica se leagă explicit.
        Gate::policy(Activity::class, ActivityPolicy::class);

        // Jurnal de audit (ce se urmărește: config/audit.php) și alerte de stoc.
        foreach (array_keys((array) config('audit.models', [])) as $class) {
            /** @var class-string<Model> $class */
            $class::observe(AuditObserver::class);
        }

        ProductVariant::observe(LowStockObserver::class);

        // Larg pentru ca retrimiterile legitime ale providerului să încapă,
        // dar suficient de strâns cât să oprească inundarea endpoint-ului.
        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(600)->by($request->ip()));
    }
}
