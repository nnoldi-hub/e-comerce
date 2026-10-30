<?php

namespace Tests\Feature\Storefront;

use Tests\Feature\Pricing\PricingTestCase;

class StorefrontPagesTest extends PricingTestCase
{
    public function test_home_lists_active_products_only(): void
    {
        $this->makeVariant(5000, product: ['name' => 'Telefon Vizibil']);
        $this->makeVariant(5000, product: ['name' => 'Telefon Ciorna', 'status' => 'draft']);
        $this->makeVariant(5000, product: ['name' => 'Telefon Viitor', 'published_at' => now()->addDay()]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Telefon Vizibil')
            ->assertDontSee('Telefon Ciorna')
            ->assertDontSee('Telefon Viitor')
            ->assertSee('<title>', false);
    }

    public function test_home_works_with_an_empty_catalog(): void
    {
        $this->get('/')->assertOk()->assertSee('Momentan nu avem produse');
    }

    public function test_empty_cart_page_renders(): void
    {
        $this->get(route('cart.index'))->assertOk()->assertSee('Coșul este gol');
    }

    public function test_cart_page_shows_items_and_the_subtotal(): void
    {
        $variant = $this->makeVariant(12100, product: ['name' => 'Casti Test']);
        $cart = $this->makeCart([[$variant, 2]]);
        // makeCart() lasă prețul depășit intenționat; coșul real îl fixează la adăugare.
        $cart->items()->update(['unit_price_snapshot' => 12100]);

        $this->withSession(['cart_ulid' => $cart->ulid])
            ->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Casti Test')
            ->assertSee('242,00 RON')
            ->assertSee(route('checkout.index'), false);
    }

    public function test_checkout_redirects_when_the_cart_is_empty(): void
    {
        $this->get(route('checkout.index'))->assertRedirect(route('cart.index'));
    }

    public function test_checkout_form_renders_with_shipping_methods_and_totals(): void
    {
        $this->makeCourier();
        $variant = $this->makeVariant(5000, product: ['name' => 'Mouse Test']);
        $cart = $this->makeCart([[$variant, 1]]);

        $this->withSession(['cart_ulid' => $cart->ulid])
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Curier')
            ->assertSee('Mouse Test')
            ->assertSee('name="idempotency_key"', false)
            ->assertSee('Plasează comanda');
    }

    public function test_checkout_errors_are_shown_next_to_the_fields(): void
    {
        $this->makeCourier();
        $variant = $this->makeVariant(5000);
        $cart = $this->makeCart([[$variant, 1]]);

        $this->withSession(['cart_ulid' => $cart->ulid])
            ->from(route('checkout.index'))
            ->post(route('checkout.store'), [])
            ->assertRedirect(route('checkout.index'));

        $this->withSession(['cart_ulid' => $cart->ulid])
            ->followingRedirects()
            ->from(route('checkout.index'))
            ->post(route('checkout.store'), [])
            ->assertSee('aria-invalid="true"', false);
    }

    public function test_authentication_pages_render(): void
    {
        foreach (['/login', '/register', '/forgot-password'] as $path) {
            $this->get($path)->assertOk()->assertSee('<form', false);
        }
    }

    public function test_pages_carry_the_security_headers_and_a_canonical_link(): void
    {
        $this->get('/')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertSee('rel="canonical"', false);
    }

    public function test_the_fortify_home_redirect_goes_to_the_storefront(): void
    {
        $this->get('/home')->assertRedirect('/cont');
    }
}
