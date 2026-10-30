<?php

namespace Tests\Feature\Storefront;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Support\Str;
use Tests\Feature\Pricing\PricingTestCase;

/**
 * Checkout-ul pe datele de demonstrație (livrare + TVA din seeder), prin HTTP.
 */
class CheckoutDemoFlowTest extends PricingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (new DemoCatalogSeeder)->withImages(false)->run();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(int $shippingMethodId): array
    {
        return [
            'email' => 'client@example.com',
            'first_name' => 'Ion',
            'last_name' => 'Popescu',
            'phone' => '0712 345 678',
            'address_line1' => 'Str. Exemplu 1',
            'city' => 'București',
            'postal_code' => '010101',
            'country' => 'RO',
            'shipping_method_id' => $shippingMethodId,
            'payment_method' => 'cod',
            'idempotency_key' => (string) Str::uuid(),
            'terms' => '1',
        ];
    }

    private function cartWith(string $productName, int $quantity = 1): string
    {
        $product = Product::query()->where('name', $productName)->firstOrFail();
        $variant = ProductVariant::query()->where('product_id', $product->id)->where('is_default', true)->firstOrFail();

        return $this->makeCart([[$variant, $quantity]])->ulid;
    }

    public function test_the_seeder_provides_a_shipping_method_and_vat(): void
    {
        $this->assertSame(1, ShippingMethod::query()->where('is_active', true)->count());

        $this->withSession(['cart_ulid' => $this->cartWith('Bec LED E27 10W')])
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Curier rapid')
            ->assertDontSee('Nicio metodă de livrare');
    }

    public function test_a_small_order_pays_shipping_and_a_large_one_ships_free(): void
    {
        $method = ShippingMethod::query()->firstOrFail();

        // 18,90 lei => transport 19,99.
        $this->withSession(['cart_ulid' => $this->cartWith('Bec LED E27 10W')])
            ->post(route('checkout.store'), $this->payload($method->id))
            ->assertRedirect(route('checkout.success'));

        $small = Order::query()->latest('id')->firstOrFail();
        $this->assertSame(1999, $small->shipping_total->amount());
        $this->assertGreaterThan(0, $small->tax_total->amount());

        // 389,00 lei => transport gratuit.
        $this->withSession(['cart_ulid' => $this->cartWith('Bormașină cu percuție 18V')])
            ->post(route('checkout.store'), $this->payload($method->id))
            ->assertRedirect(route('checkout.success'));

        $large = Order::query()->latest('id')->firstOrFail();
        $this->assertSame(0, $large->shipping_total->amount());
    }

    public function test_validation_messages_use_romanian_field_names(): void
    {
        $this->withSession(['cart_ulid' => $this->cartWith('Bec LED E27 10W')])
            ->post(route('checkout.store'), [])
            ->assertSessionHasErrors(['shipping_method_id', 'first_name', 'terms']);

        $errors = session('errors')->getBag('default');

        $this->assertStringContainsString('metoda de livrare', (string) $errors->first('shipping_method_id'));
        $this->assertStringContainsString('prenumele', (string) $errors->first('first_name'));
        $this->assertStringNotContainsString('shipping method id', (string) $errors->first('shipping_method_id'));
    }

    public function test_company_fields_are_hidden_until_the_box_is_ticked(): void
    {
        $this->withSession(['cart_ulid' => $this->cartWith('Bec LED E27 10W')])
            ->get(route('checkout.index'))
            ->assertSee('peer-checked:grid', false)
            ->assertSee('id="is_company"', false);
    }
}
