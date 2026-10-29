<?php

namespace Tests\Feature\Checkout;

use App\Models\Order;
use Illuminate\Support\Str;
use Tests\Feature\Pricing\PricingTestCase;

class CheckoutControllerTest extends PricingTestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(int $shippingMethodId, array $overrides = []): array
    {
        return array_merge([
            'email' => 'client@example.com',
            'first_name' => 'Ion',
            'last_name' => 'Popescu',
            'phone' => '0712 345 678',
            'address_line1' => 'Str. Exemplu 1',
            'city' => 'București',
            'postal_code' => '010101',
            'country' => 'ro',
            'shipping_method_id' => $shippingMethodId,
            'payment_method' => 'card',
            'idempotency_key' => (string) Str::uuid(),
            'terms' => '1',
        ], $overrides);
    }

    public function test_a_guest_can_place_an_order(): void
    {
        $courier = $this->makeCourier();
        $cart = $this->makeCart([[$this->makeVariant(12100, stock: 5), 1]]);

        $this->withSession(['cart_ulid' => $cart->ulid])
            ->post(route('checkout.store'), $this->payload($courier->id))
            ->assertRedirect(route('checkout.success'))
            ->assertSessionHas('order_number');

        $order = Order::query()->firstOrFail();
        $this->assertSame('client@example.com', $order->email);
        $this->assertSame('RO', $order->shipping_address['country']);
        $this->assertNull($order->user_id);
    }

    public function test_required_fields_are_validated(): void
    {
        $this->post(route('checkout.store'), [])->assertSessionHasErrors([
            'email', 'first_name', 'last_name', 'phone', 'address_line1', 'city',
            'postal_code', 'country', 'shipping_method_id', 'payment_method',
            'idempotency_key', 'terms',
        ]);

        $this->assertSame(0, Order::query()->count());
    }

    public function test_a_company_needs_a_valid_cui(): void
    {
        $courier = $this->makeCourier();
        $cart = $this->makeCart([[$this->makeVariant(5000), 1]]);
        $session = ['cart_ulid' => $cart->ulid];

        $this->withSession($session)
            ->post(route('checkout.store'), $this->payload($courier->id, [
                'is_company' => '1', 'company_name' => 'Firma SRL', 'cui' => '1234566',
            ]))
            ->assertSessionHasErrors('cui');

        $this->withSession($session)
            ->post(route('checkout.store'), $this->payload($courier->id, [
                'is_company' => '1', 'company_name' => 'Firma SRL', 'cui' => 'RO1234565',
            ]))
            ->assertRedirect(route('checkout.success'));

        $order = Order::query()->firstOrFail();
        $this->assertSame('Firma SRL', $order->billing_address['company_name']);
        $this->assertSame('RO1234565', $order->billing_address['cui']);
    }

    public function test_an_invalid_coupon_is_reported_on_the_coupon_field(): void
    {
        $courier = $this->makeCourier();
        $cart = $this->makeCart([[$this->makeVariant(5000), 1]]);

        $this->withSession(['cart_ulid' => $cart->ulid])
            ->post(route('checkout.store'), $this->payload($courier->id, ['coupon_code' => 'NU-EXISTA']))
            ->assertSessionHasErrors('coupon_code');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_insufficient_stock_is_reported(): void
    {
        $courier = $this->makeCourier();
        $cart = $this->makeCart([[$this->makeVariant(5000, stock: 1), 3]]);

        $this->withSession(['cart_ulid' => $cart->ulid])
            ->post(route('checkout.store'), $this->payload($courier->id))
            ->assertSessionHasErrors('stock');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_double_submit_with_the_same_key_creates_one_order(): void
    {
        $courier = $this->makeCourier();
        $cart = $this->makeCart([[$this->makeVariant(5000, stock: 5), 1]]);
        $payload = $this->payload($courier->id);

        $this->withSession(['cart_ulid' => $cart->ulid])->post(route('checkout.store'), $payload);
        $this->withSession(['cart_ulid' => $cart->ulid])
            ->post(route('checkout.store'), $payload)
            ->assertRedirect(route('checkout.success'));

        $this->assertSame(1, Order::query()->count());
    }
}
