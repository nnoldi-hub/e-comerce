<?php

namespace Tests\Feature\Payments;

use App\Domain\Checkout\Actions\PlaceOrderAction;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\Feature\Pricing\PricingTestCase;

class PayOrderRouteTest extends PricingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        config([
            'shop.payments.methods.card' => 'stripe',
            'shop.payments.gateways.stripe.secret' => 'sk_test_123',
            'shop.payments.gateways.stripe.webhook_secret' => 'whsec_test',
        ]);

        Http::fake(['api.stripe.com/*' => Http::response([
            'id' => 'cs_test_123',
            'url' => 'https://checkout.stripe.test/cs_test_123',
        ])]);
    }

    private function placeCardOrder(): Order
    {
        $courier = $this->makeCourier();
        $variant = $this->makeVariant(12100, stock: 10);
        $cart = $this->makeCart([[$variant, 2]]);

        return $this->app->make(PlaceOrderAction::class)->execute(
            $cart,
            $this->makeOrderData($courier->id, ['paymentMethod' => 'card']),
        );
    }

    private function signedPayUrl(Order $order): string
    {
        return URL::temporarySignedRoute('checkout.pay', now()->addHours(24), ['order' => $order->number]);
    }

    public function test_an_unsigned_request_is_forbidden(): void
    {
        $order = $this->placeCardOrder();

        $this->post(route('checkout.pay', ['order' => $order->number]))->assertForbidden();
    }

    public function test_a_signed_request_redirects_to_the_payment_page(): void
    {
        $order = $this->placeCardOrder();

        $this->post($this->signedPayUrl($order))
            ->assertRedirect('https://checkout.stripe.test/cs_test_123');
    }

    public function test_an_expired_link_is_forbidden(): void
    {
        $order = $this->placeCardOrder();
        $url = $this->signedPayUrl($order);

        $this->travel(25)->hours();

        $this->post($url)->assertForbidden();
    }

    public function test_a_closed_order_shows_an_error_instead_of_a_payment_page(): void
    {
        $order = $this->placeCardOrder();
        $order->update(['status' => OrderStatus::CANCELLED]);

        $this->from('/checkout/success')
            ->post($this->signedPayUrl($order))
            ->assertRedirect('/checkout/success')
            ->assertSessionHas('payment_error');
    }
}
