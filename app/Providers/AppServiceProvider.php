<?php

namespace App\Providers;

use App\Domain\Ops\HealthCheck;
use App\Domain\Payments\Gateways\StripeGateway;
use App\Http\Middleware\ConfigureTrustedProxies;
use App\Http\Middleware\SecurityHeaders;
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
use Filament\Facades\Filament;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Http\Kernel as FoundationKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        // Panoul de administrare este în română; vitrina își păstrează limba aplicației.
        // Limba se poate schimba cu `app.admin_locale` (implicit `ro`).
        Filament::serving(function (): void {
            app()->setLocale((string) config('app.admin_locale', 'ro'));
        });

        // Middleware global (și panoul, care nu trece prin grupul `web`). Ordinea
        // contează: întâi proxy-urile de încredere, apoi headerele (HSTS depinde de isSecure()).
        $kernel = $this->app->make(HttpKernel::class);

        if ($kernel instanceof FoundationKernel) {
            $kernel->pushMiddleware(ConfigureTrustedProxies::class);
            $kernel->pushMiddleware(SecurityHeaders::class);
        }

        $this->registerRateLimiters();
        $this->registerOperationalSchedule();
    }

    private function registerRateLimiters(): void
    {
        // Larg pentru ca retrimiterile legitime ale providerului să încapă,
        // dar suficient de strâns cât să oprească inundarea endpoint-ului.
        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(600)->by($request->ip()));

        // Limitele se citesc la fiecare cerere din config/security.php.
        RateLimiter::for('cart', fn (Request $request) => Limit::perMinute((int) config('security.rate_limits.cart', 120))->by((string) $request->ip()));
        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute((int) config('security.rate_limits.checkout', 20))->by((string) $request->ip()));
    }

    /**
     * Sarcini operaționale ale platformei (nu ale domeniului): bătaia de inimă a
     * planificatorului, citită de `ops:check` și de /health, și curățarea joburilor
     * eșuate vechi. Sarcinile de domeniu stau în routes/console.php.
     */
    private function registerOperationalSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->call(function (): void {
                Cache::put(HealthCheck::HEARTBEAT_KEY, now()->timestamp, 600);
            })->name('ops-heartbeat')->everyMinute();

            $schedule->command('queue:prune-failed --hours=168')->daily();
        });
    }
}
