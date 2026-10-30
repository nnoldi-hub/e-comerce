<?php

namespace Tests\Feature\Smoke;

use Tests\Feature\Pricing\PricingTestCase;

/**
 * Rutele publice ale vitrinei răspund corect: pagini, SEO, rute protejate și 404.
 * Detaliile fiecărei pagini sunt în testele modulelor; aici prindem o rută ruptă.
 */
class StorefrontRoutesTest extends PricingTestCase
{
    public function test_public_pages_respond(): void
    {
        foreach (['/', '/produse', '/cart', '/login', '/register', '/forgot-password', '/sitemap.xml', '/robots.txt'] as $path) {
            $this->get($path)->assertSuccessful();
        }
    }

    public function test_protected_and_empty_state_routes_redirect(): void
    {
        $this->get('/checkout')->assertRedirect(route('cart.index'));
        $this->get('/checkout/success')->assertRedirect(route('cart.index'));

        foreach (['/cont', '/cont/comenzi', '/cont/adrese', '/cont/profil', '/cont/parola'] as $path) {
            $this->get($path)->assertRedirect(route('login'));
        }

        $this->get('/admin')->assertRedirect();
    }

    public function test_unknown_addresses_give_a_custom_404(): void
    {
        foreach (['/nu-exista', '/produs/nu-exista', '/categorie/nu-exista', '/pagina/nu-exista'] as $path) {
            $this->get($path)->assertNotFound();
        }
    }
}
