<?php

namespace Tests\Feature\Storefront;

use App\Domain\Catalog\ProductAggregates;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Feature\Pricing\PricingTestCase;

class ReviewsWishlistBestsellersTest extends PricingTestCase
{
    private ProductVariant $lastVariant;

    private function item(string $name = 'Priza dubla', int $price = 25000): Product
    {
        $this->lastVariant = $this->makeVariant($price, 10, [], ['name' => $name, 'published_at' => now()->subDays(40)]);

        app(ProductAggregates::class)->refresh($this->lastVariant->product_id);

        return Product::query()->findOrFail($this->lastVariant->product_id);
    }

    private function customer(): User
    {
        return User::factory()->create(['is_active' => true]);
    }

    private function review(Product $product, User $user, int $rating, string $status = Review::APPROVED): Review
    {
        return Review::query()->create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'rating' => $rating,
            'body' => 'Produs foarte bun, recomand cu încredere.',
            'status' => $status,
        ]);
    }

    private function placeOrder(User $user, ProductVariant $variant, string $status = 'paid', int $quantity = 1, ?string $placedAt = null): void
    {
        $orderId = DB::table('orders')->insertGetId([
            'number' => 'T-'.random_int(100000, 999999),
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => $status,
            'payment_status' => 'paid',
            'subtotal' => 1000,
            'grand_total' => 1000,
            'placed_at' => $placedAt ?? now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_variant_id' => $variant->id,
            'name' => 'Linie',
            'sku' => 'SKU-'.$orderId,
            'unit_price' => 1000,
            'quantity' => $quantity,
            'line_total' => 1000 * $quantity,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ---- Favorite ----

    public function test_guests_are_sent_to_login_when_toggling_a_favorite(): void
    {
        $product = $this->item();

        $this->post(route('account.wishlist.toggle', $product))->assertRedirect(route('login'));
        $this->assertDatabaseCount('wishlist_items', 0);
    }

    public function test_a_customer_can_add_and_remove_a_favorite(): void
    {
        $product = $this->item();
        $user = $this->customer();

        $this->actingAs($user)->post(route('account.wishlist.toggle', $product))->assertRedirect();
        $this->assertDatabaseHas('wishlist_items', ['user_id' => $user->id, 'product_id' => $product->id]);

        app()->forgetScopedInstances();

        $this->actingAs($user)->post(route('account.wishlist.toggle', $product))->assertRedirect();
        $this->assertDatabaseMissing('wishlist_items', ['user_id' => $user->id, 'product_id' => $product->id]);
    }

    public function test_wishlist_page_lists_only_my_products_and_the_product_page_shows_the_heart_pressed(): void
    {
        $mine = $this->item('Priza mea');
        $other = $this->item('Priza altuia');
        $user = $this->customer();
        WishlistItem::query()->create(['user_id' => $user->id, 'product_id' => $mine->id]);
        WishlistItem::query()->create(['user_id' => $this->customer()->id, 'product_id' => $other->id]);

        $this->actingAs($user)->get(route('account.wishlist.index'))
            ->assertOk()->assertSee('Priza mea')->assertDontSee('Priza altuia');

        app()->forgetScopedInstances();

        $this->actingAs($user)->get(route('catalog.product', $mine))->assertOk()->assertSee('aria-pressed="true"', false);
    }

    // ---- Recenzii ----

    public function test_guests_cannot_post_reviews(): void
    {
        $product = $this->item();

        $this->post(route('reviews.store', $product), ['rating' => 5, 'body' => 'Foarte bun, recomand.'])->assertRedirect(route('login'));
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_review_input_is_validated(): void
    {
        $product = $this->item();

        $this->actingAs($this->customer())->post(route('reviews.store', $product), ['rating' => 9, 'body' => 'scurt'])
            ->assertSessionHasErrors(['rating', 'body']);
    }

    public function test_a_review_starts_pending_and_only_shows_after_approval(): void
    {
        $product = $this->item();
        $user = $this->customer();

        $this->actingAs($user)->post(route('reviews.store', $product), ['rating' => 5, 'title' => 'Excelent', 'body' => 'Funcționează perfect, recomand.'])
            ->assertRedirect(route('catalog.product', $product).'#recenzii');

        $review = Review::query()->firstOrFail();
        $this->assertSame(Review::PENDING, $review->status);
        $this->assertSame(0, (int) Product::query()->findOrFail($product->id)->reviews_count);

        // Autorul își vede textul în formularul de editare, deci verificăm ca un alt client.
        $reader = $this->customer();
        $this->actingAs($reader)->get(route('catalog.product', $product))->assertOk()->assertDontSee('Funcționează perfect');

        $review->update(['status' => Review::APPROVED]);

        $fresh = Product::query()->findOrFail($product->id);
        $this->assertSame(1, (int) $fresh->reviews_count);
        $this->assertEqualsWithDelta(5.0, (float) $fresh->rating_avg, 0.001);

        $this->actingAs($reader)->get(route('catalog.product', $product))
            ->assertOk()
            ->assertSee('Funcționează perfect')
            ->assertSee('"aggregateRating"', false);
    }

    public function test_resubmitting_replaces_the_review_and_sends_it_back_to_moderation(): void
    {
        $product = $this->item();
        $user = $this->customer();
        $this->review($product, $user, 5);

        $this->actingAs($user)->post(route('reviews.store', $product), ['rating' => 3, 'body' => 'Mi-am schimbat părerea după o lună.']);

        $this->assertDatabaseCount('reviews', 1);
        $review = Review::query()->firstOrFail();
        $this->assertSame(3, $review->rating);
        $this->assertSame(Review::PENDING, $review->status);
        $this->assertSame(0, (int) Product::query()->findOrFail($product->id)->reviews_count);
    }

    public function test_average_and_count_use_only_approved_reviews(): void
    {
        $product = $this->item();
        $this->review($product, $this->customer(), 5);
        $this->review($product, $this->customer(), 4);
        $this->review($product, $this->customer(), 1, Review::PENDING);
        $this->review($product, $this->customer(), 1, Review::REJECTED);

        $fresh = Product::query()->findOrFail($product->id);
        $this->assertSame(2, (int) $fresh->reviews_count);
        $this->assertEqualsWithDelta(4.5, (float) $fresh->rating_avg, 0.001);
    }

    public function test_verified_purchase_is_flagged_only_with_a_real_order(): void
    {
        $product = $this->item();
        $buyer = $this->customer();
        $visitor = $this->customer();
        $this->placeOrder($buyer, $this->lastVariant);

        $this->actingAs($buyer)->post(route('reviews.store', $product), ['rating' => 5, 'body' => 'Am cumpărat și sunt mulțumit.']);
        $this->actingAs($visitor)->post(route('reviews.store', $product), ['rating' => 4, 'body' => 'Nu l-am cumpărat încă de aici.']);

        $this->assertTrue(Review::query()->where('user_id', $buyer->id)->firstOrFail()->verified_purchase);
        $this->assertFalse(Review::query()->where('user_id', $visitor->id)->firstOrFail()->verified_purchase);
    }

    public function test_product_cards_show_the_rating(): void
    {
        $product = $this->item('Priza cu nota');
        $this->review($product, $this->customer(), 4);

        $this->get(route('catalog.index'))->assertOk()->assertSee('4,0 (1)');
    }

    public function test_staff_can_approve_a_review_from_the_admin(): void
    {
        Gate::before(fn () => true);

        $product = $this->item();
        $review = $this->review($product, $this->customer(), 5, Review::PENDING);

        $this->actingAs(User::factory()->create());

        Livewire::test(ListReviews::class)
            ->assertCanSeeTableRecords([$review])
            ->callTableAction('approve', $review);

        $this->assertSame(Review::APPROVED, $review->refresh()->status);
        $this->assertSame(1, (int) Product::query()->findOrFail($product->id)->reviews_count);
    }

    // ---- Bestseller ----

    public function test_bestsellers_section_and_badge_appear_for_recent_paid_sales(): void
    {
        Cache::forget('storefront.bestsellers');

        $seller = $this->item('Priza vedeta');
        $this->item('Priza lenesa');
        $this->placeOrder($this->customer(), $this->lastVariantOf($seller), 'paid', 3);
        Cache::forget('storefront.bestsellers');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Cele mai vândute')
            ->assertSee('Bestseller');
    }

    public function test_old_unpaid_or_cancelled_sales_do_not_make_a_bestseller(): void
    {
        Cache::forget('storefront.bestsellers');

        $product = $this->item();
        $variant = $this->lastVariant;
        $user = $this->customer();
        $this->placeOrder($user, $variant, 'cancelled');
        $this->placeOrder($user, $variant, 'pending');
        $this->placeOrder($user, $variant, 'paid', 1, now()->subDays(200)->toDateTimeString());
        Cache::forget('storefront.bestsellers');

        $this->get(route('home'))->assertOk()->assertDontSee('Cele mai vândute');
        $this->assertNotNull($product);
    }

    private function lastVariantOf(Product $product): ProductVariant
    {
        return ProductVariant::query()->where('product_id', $product->id)->firstOrFail();
    }
}
