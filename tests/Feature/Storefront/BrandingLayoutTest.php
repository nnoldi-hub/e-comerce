<?php

namespace Tests\Feature\Storefront;

use App\Models\Cart;
use App\Models\User;
use App\Support\ShopChrome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BrandingLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_is_branded_and_has_the_main_landmarks(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(config('app.name'))
            ->assertSee('Sari la conținut')
            ->assertSee('name="theme-color"', false)
            ->assertSee('favicon.svg', false)
            ->assertSee('aria-label="Meniu"', false)
            ->assertSee('Produse noi')
            ->assertSee('Autentificare');
    }

    public function test_the_brand_favicon_is_shipped(): void
    {
        $this->assertFileExists(public_path('favicon.svg'));
    }

    public function test_browsing_the_storefront_never_creates_a_cart(): void
    {
        $this->get('/')->assertOk();
        $this->get('/produse')->assertOk();
        $this->get('/pagina-care-nu-exista')->assertNotFound();

        // Contorul din header este doar citire: fără `firstOrCreate` pe fiecare vizită.
        $this->assertDatabaseCount('carts', 0);
    }

    public function test_the_catalog_has_a_filter_toggle_for_small_screens(): void
    {
        $this->get('/produse')
            ->assertOk()
            ->assertSee('id="filters-toggle"', false)
            ->assertSee('Filtre și categorii');
    }

    public function test_the_cart_counter_is_zero_without_a_cart(): void
    {
        $this->assertSame(0, app(ShopChrome::class)->cartCount());
    }

    public function test_the_cart_counter_sums_the_quantities_of_the_visitors_cart_only(): void
    {
        $this->startSession();
        $sessionId = session()->getId();

        $mine = Cart::query()->create(['session_id' => $sessionId, 'currency' => 'RON']);
        $other = Cart::query()->create(['session_id' => 'another-visitor', 'currency' => 'RON']);

        // Nu ne interesează produsele, doar cantitățile din coș.
        Schema::disableForeignKeyConstraints();
        $mine->items()->create(['product_variant_id' => 9001, 'quantity' => 2, 'unit_price_snapshot' => 1000]);
        $mine->items()->create(['product_variant_id' => 9002, 'quantity' => 3, 'unit_price_snapshot' => 500]);
        $other->items()->create(['product_variant_id' => 9001, 'quantity' => 7, 'unit_price_snapshot' => 1000]);
        Schema::enableForeignKeyConstraints();

        $this->assertSame(5, app(ShopChrome::class)->cartCount());
    }

    public function test_the_cart_counter_follows_the_signed_in_customer(): void
    {
        $user = User::factory()->create();
        $cart = Cart::query()->create(['user_id' => $user->id, 'session_id' => 'old-session', 'currency' => 'RON']);

        Schema::disableForeignKeyConstraints();
        $cart->items()->create(['product_variant_id' => 9001, 'quantity' => 4, 'unit_price_snapshot' => 1000]);
        Schema::enableForeignKeyConstraints();

        $this->actingAs($user);

        $this->assertSame(4, app(ShopChrome::class)->cartCount());
    }
}
