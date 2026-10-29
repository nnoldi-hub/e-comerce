<?php

namespace Tests\Feature\Checkout;

use App\Models\Cart;
use App\Models\CartItem;
use Tests\Feature\Pricing\PricingTestCase;

class CartControllerTest extends PricingTestCase
{
    public function test_adding_a_variant_creates_a_guest_cart_in_the_session(): void
    {
        $variant = $this->makeVariant(5000);

        $this->post(route('cart.add', $variant), ['quantity' => 2])
            ->assertRedirect()
            ->assertSessionHas('cart_ulid');

        $item = CartItem::query()->firstOrFail();
        $this->assertSame($variant->id, (int) $item->product_variant_id);
        $this->assertSame(2, $item->quantity);
    }

    public function test_quantity_is_validated(): void
    {
        $variant = $this->makeVariant(5000);

        $this->post(route('cart.add', $variant), ['quantity' => 0])->assertSessionHasErrors('quantity');
        $this->post(route('cart.add', $variant), ['quantity' => 1000])->assertSessionHasErrors('quantity');

        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_updating_the_quantity_to_zero_removes_the_item(): void
    {
        $cart = $this->makeCart([[$this->makeVariant(5000), 2]]);
        $item = CartItem::query()->firstOrFail();

        $this->withSession(['cart_ulid' => $cart->ulid])
            ->patch(route('cart.update', $item), ['quantity' => 0])
            ->assertRedirect();

        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_a_customer_cannot_modify_items_from_another_cart(): void
    {
        $mine = $this->makeCart([]);
        $theirs = $this->makeCart([[$this->makeVariant(5000), 1]]);
        $item = CartItem::query()->where('cart_id', $theirs->id)->firstOrFail();

        $this->withSession(['cart_ulid' => $mine->ulid])
            ->patch(route('cart.update', $item), ['quantity' => 5])
            ->assertForbidden();

        $this->withSession(['cart_ulid' => $mine->ulid])
            ->delete(route('cart.remove', $item))
            ->assertForbidden();

        $this->assertSame(1, $item->fresh()->quantity);
        $this->assertSame(1, Cart::query()->where('id', $theirs->id)->count());
    }
}
