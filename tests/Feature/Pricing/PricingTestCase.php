<?php

namespace Tests\Feature\Pricing;

use App\Domain\Checkout\DTOs\PlaceOrderData;
use App\Domain\Pricing\DTOs\CartTotals;
use App\Domain\Pricing\DTOs\PricingInput;
use App\Domain\Pricing\PriceCalculator;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\TaxRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

abstract class PricingTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * RefreshDatabase rulează migrate:fresh. Refuzăm să pornim dacă baza
     * de test nu se termină în "_test", ca să nu ștergem baza de dezvoltare.
     */
    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException(
                "Baza '{$database}' nu pare a fi de test. Setează în phpunit.xml DB_CONNECTION=mysql și DB_DATABASE=e_comerce_test."
            );
        }
    }

    /**
     * @param  array<string, mixed>  $variant  suprascrieri pentru variantă
     * @param  array<string, mixed>  $product  suprascrieri pentru produs
     */
    protected function makeVariant(int $price, int $stock = 100, array $variant = [], array $product = []): ProductVariant
    {
        $productModel = Product::forceCreate(array_merge([
            'ulid' => (string) Str::ulid(),
            'name' => 'Produs '.Str::random(6),
            'slug' => 'produs-'.Str::lower(Str::random(10)),
            'status' => 'active',
            'published_at' => now()->subDay(),
        ], $product));

        return ProductVariant::forceCreate(array_merge([
            'product_id' => $productModel->id,
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'price' => $price,
            'stock_on_hand' => $stock,
        ], $variant));
    }

    /**
     * @param  list<array{0: ProductVariant, 1: int}>  $lines
     */
    protected function makeCart(array $lines): Cart
    {
        $cart = Cart::create(['currency' => 'RON']);

        foreach ($lines as [$variant, $quantity]) {
            CartItem::create([
                'cart_id' => $cart->id,
                'product_variant_id' => $variant->id,
                'quantity' => $quantity,
                'unit_price_snapshot' => 1, // depășit intenționat
            ]);
        }

        return $cart;
    }

    protected function makeTax(int $bps, bool $inclusive = true, string $country = 'RO'): TaxRate
    {
        return TaxRate::forceCreate([
            'name' => "TVA {$bps}",
            'rate_bps' => $bps,
            'country' => $country,
            'is_inclusive' => $inclusive,
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeCoupon(array $attributes = []): Coupon
    {
        return Coupon::forceCreate(array_merge([
            'code' => 'T'.Str::upper(Str::random(7)),
            'type' => 'percent',
            'value' => 1000,
            'is_active' => true,
        ], $attributes));
    }

    /** Curier în RO: 0–19999 => 1999; de la 20000 => gratuit. */
    protected function makeCourier(): ShippingMethod
    {
        $zone = ShippingZone::forceCreate(['name' => 'România', 'countries' => ['RO'], 'is_active' => true]);
        $method = ShippingMethod::forceCreate(['shipping_zone_id' => $zone->id, 'name' => 'Curier', 'is_active' => true]);

        ShippingRate::forceCreate(['shipping_method_id' => $method->id, 'min_subtotal' => 0, 'max_subtotal' => 19999, 'price' => 1999]);
        ShippingRate::forceCreate(['shipping_method_id' => $method->id, 'min_subtotal' => 20000, 'max_subtotal' => null, 'price' => 0]);

        return $method;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeOrderData(int $shippingMethodId, array $overrides = []): PlaceOrderData
    {
        $address = [
            'first_name' => 'Ion',
            'last_name' => 'Popescu',
            'phone' => '0712345678',
            'address_line1' => 'Str. Exemplu 1',
            'city' => 'București',
            'postal_code' => '010101',
            'country' => 'RO',
        ];

        return new PlaceOrderData(...array_merge([
            'email' => 'client@example.com',
            'billingAddress' => $address,
            'shippingAddress' => $address,
            'shippingMethodId' => $shippingMethodId,
            'paymentMethod' => 'card',
            'idempotencyKey' => (string) Str::uuid(),
        ], $overrides));
    }

    protected function calculate(Cart $cart, ?PricingInput $input = null): CartTotals
    {
        return app(PriceCalculator::class)->calculate($cart, $input);
    }
}
