<?php

namespace Tests\Feature\Storefront;

use App\Domain\Account\AddressBook;
use App\Domain\Checkout\Actions\PlaceOrderAction;
use App\Models\Address;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Str;
use Tests\Feature\Pricing\PricingTestCase;

class AddressBookTest extends PricingTestCase
{
    private function customer(array $overrides = []): User
    {
        return User::factory()->create(array_merge(['is_active' => true, 'email_verified_at' => now()], $overrides))->fresh();
    }

    /** @return array<string, mixed> */
    private function form(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Acasă',
            'first_name' => 'Ion', 'last_name' => 'Popescu', 'phone' => '0712 345 678',
            'address_line1' => 'Str. Exemplu 1', 'city' => 'București', 'postal_code' => '010101', 'country' => 'RO',
        ], $overrides);
    }

    private function address(User $user, array $overrides = []): Address
    {
        return app(AddressBook::class)->save($user->id, $this->form($overrides));
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/cont/adrese')->assertRedirect(route('login'));
        $this->post('/cont/adrese', $this->form())->assertRedirect(route('login'));
    }

    public function test_an_address_can_be_added_and_the_first_one_becomes_the_default(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->post('/cont/adrese', $this->form())->assertRedirect(route('account.addresses.index'));

        $address = Address::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertTrue($address->is_default);
        $this->assertSame('RO', $address->country);

        $this->actingAs($user)->get('/cont/adrese')->assertOk()->assertSee('Str. Exemplu 1')->assertSee('Implicită');
    }

    public function test_a_new_default_replaces_the_old_one(): void
    {
        $user = $this->customer();
        $first = $this->address($user);

        $this->actingAs($user)->post('/cont/adrese', $this->form(['label' => 'Birou', 'address_line1' => 'Str. B 2', 'is_default' => '1']));

        $second = Address::query()->where('label', 'Birou')->firstOrFail();
        $this->assertTrue($second->is_default);
        $this->assertFalse($first->fresh()->is_default);
        $this->assertSame(1, Address::query()->where('user_id', $user->id)->where('is_default', true)->count());
    }

    public function test_company_data_is_kept_only_when_the_company_box_is_checked(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->post('/cont/adrese', $this->form(['is_company' => '1', 'company_name' => 'Firma SRL', 'cui' => 'RO1234565']));
        $this->actingAs($user)->post('/cont/adrese', $this->form(['address_line1' => 'Str. C 3', 'company_name' => 'Ignorata SRL', 'cui' => 'RO1234565']));

        $this->assertSame('Firma SRL', Address::query()->where('address_line1', 'Str. Exemplu 1')->firstOrFail()->company_name);
        $this->assertNull(Address::query()->where('address_line1', 'Str. C 3')->firstOrFail()->company_name);
    }

    public function test_invalid_input_is_rejected(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->post('/cont/adrese', [])->assertSessionHasErrors(['first_name', 'last_name', 'phone', 'address_line1', 'city', 'postal_code']);
        $this->actingAs($user)->post('/cont/adrese', $this->form(['is_company' => '1', 'company_name' => 'F', 'cui' => '1234566']))->assertSessionHasErrors('cui');
        $this->assertSame(0, Address::query()->count());
    }

    public function test_other_customers_addresses_are_not_found_and_stay_unchanged(): void
    {
        $me = $this->customer();
        $theirs = $this->address($this->customer(), ['address_line1' => 'Secret 9']);

        $this->actingAs($me)->get("/cont/adrese/{$theirs->id}/editeaza")->assertNotFound();
        $this->actingAs($me)->put("/cont/adrese/{$theirs->id}", $this->form(['address_line1' => 'Furat']))->assertNotFound();
        $this->actingAs($me)->delete("/cont/adrese/{$theirs->id}")->assertNotFound();
        $this->actingAs($me)->post("/cont/adrese/{$theirs->id}/implicita")->assertNotFound();
        $this->actingAs($me)->get('/cont/adrese')->assertDontSee('Secret 9');

        $this->assertSame('Secret 9', $theirs->fresh()->address_line1);
    }

    public function test_an_address_can_be_edited(): void
    {
        $user = $this->customer();
        $address = $this->address($user);

        $this->actingAs($user)->get("/cont/adrese/{$address->id}/editeaza")->assertOk()->assertSee('Str. Exemplu 1');
        $this->actingAs($user)->put("/cont/adrese/{$address->id}", $this->form(['city' => 'Cluj-Napoca']))->assertRedirect(route('account.addresses.index'));

        $this->assertSame('Cluj-Napoca', $address->fresh()->city);
        $this->assertTrue($address->fresh()->is_default, 'singura adresă rămâne implicită');
    }

    public function test_deleting_the_default_promotes_the_oldest_remaining_address(): void
    {
        $user = $this->customer();
        $first = $this->address($user);
        $second = $this->address($user, ['address_line1' => 'Str. B 2']);
        $third = $this->address($user, ['address_line1' => 'Str. C 3', 'is_default' => true]);

        $this->actingAs($user)->delete("/cont/adrese/{$third->id}")->assertRedirect();

        $this->assertNull(Address::query()->find($third->id));
        $this->assertTrue($first->fresh()->is_default);
        $this->assertFalse($second->fresh()->is_default);
    }

    public function test_the_address_limit_is_enforced(): void
    {
        $user = $this->customer();

        for ($i = 0; $i < AddressBook::LIMIT; $i++) {
            $this->address($user, ['address_line1' => "Str. {$i}"]);
        }

        $this->actingAs($user)->post('/cont/adrese', $this->form(['address_line1' => 'Una în plus']))->assertSessionHasErrors('limit');
        $this->assertSame(AddressBook::LIMIT, Address::query()->where('user_id', $user->id)->count());
    }

    public function test_order_addresses_are_copies_and_do_not_follow_the_book(): void
    {
        $user = $this->customer();
        $address = $this->address($user);
        $courier = $this->makeCourier();
        $cart = $this->makeCart([[$this->makeVariant(5000, stock: 5), 1]]);

        $this->actingAs($user)->withSession(['cart_ulid' => $cart->ulid])->post(route('checkout.store'), $this->checkoutPayload($courier->id));

        $this->actingAs($user)->put("/cont/adrese/{$address->id}", $this->form(['address_line1' => 'Alta 99']));

        $this->assertSame('Str. Exemplu 1', Order::query()->firstOrFail()->shipping_address['address_line1']);
    }

    /** @return array<string, mixed> */
    private function checkoutPayload(int $shippingMethodId, array $overrides = []): array
    {
        return array_merge([
            'email' => 'client@example.com',
            'first_name' => 'Ion', 'last_name' => 'Popescu', 'phone' => '0712 345 678',
            'address_line1' => 'Str. Exemplu 1', 'city' => 'București', 'postal_code' => '010101', 'country' => 'RO',
            'shipping_method_id' => $shippingMethodId, 'payment_method' => 'cod',
            'idempotency_key' => (string) Str::uuid(), 'terms' => '1',
        ], $overrides);
    }

    public function test_checkout_saves_the_address_only_when_asked_and_without_duplicates(): void
    {
        $user = $this->customer();
        $courier = $this->makeCourier();

        $cart = $this->makeCart([[$this->makeVariant(5000, stock: 9), 1]]);
        $this->actingAs($user)->withSession(['cart_ulid' => $cart->ulid])->post(route('checkout.store'), $this->checkoutPayload($courier->id));
        $this->assertSame(0, Address::query()->count(), 'fără bifă nu se salvează');

        $cart = $this->makeCart([[$this->makeVariant(5000, stock: 9), 1]]);
        $this->actingAs($user)->withSession(['cart_ulid' => $cart->ulid])->post(route('checkout.store'), $this->checkoutPayload($courier->id, ['save_address' => '1']));
        $this->assertSame(1, Address::query()->where('user_id', $user->id)->count());

        $cart = $this->makeCart([[$this->makeVariant(5000, stock: 9), 1]]);
        $this->actingAs($user)->withSession(['cart_ulid' => $cart->ulid])->post(route('checkout.store'), $this->checkoutPayload($courier->id, ['save_address' => '1']));
        $this->assertSame(1, Address::query()->where('user_id', $user->id)->count(), 'aceeași adresă nu se dublează');
    }

    public function test_a_guest_checkout_saves_nothing(): void
    {
        $courier = $this->makeCourier();
        $cart = $this->makeCart([[$this->makeVariant(5000, stock: 5), 1]]);

        $this->withSession(['cart_ulid' => $cart->ulid])->post(route('checkout.store'), $this->checkoutPayload($courier->id, ['save_address' => '1']))->assertRedirect(route('checkout.success'));

        $this->assertSame(0, Address::query()->count());
    }

    public function test_checkout_is_prefilled_from_the_default_or_the_chosen_address(): void
    {
        $user = $this->customer();
        $this->address($user, ['label' => 'Acasă', 'address_line1' => 'Str. Implicită 1']);
        $other = $this->address($user, ['label' => 'Birou', 'address_line1' => 'Str. Birou 7', 'city' => 'Iași']);
        $this->makeCourier();
        $cart = $this->makeCart([[$this->makeVariant(5000, stock: 5), 1]]);

        $this->actingAs($user)->withSession(['cart_ulid' => $cart->ulid])->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('value="Str. Implicită 1"', false)
            ->assertSee('Adresele tale salvate');

        $this->actingAs($user)->withSession(['cart_ulid' => $cart->ulid])->get(route('checkout.index', ['adresa' => $other->id]))
            ->assertSee('value="Str. Birou 7"', false)
            ->assertSee('value="Iași"', false);
    }

    public function test_someone_elses_address_id_is_ignored_in_checkout(): void
    {
        $user = $this->customer();
        $theirs = $this->address($this->customer(), ['address_line1' => 'Secret 9']);
        $this->makeCourier();
        $cart = $this->makeCart([[$this->makeVariant(5000, stock: 5), 1]]);

        $this->actingAs($user)->withSession(['cart_ulid' => $cart->ulid])->get(route('checkout.index', ['adresa' => $theirs->id]))
            ->assertOk()
            ->assertDontSee('Secret 9');
    }

    private function guestOrder(string $email, ?int $userId = null): Order
    {
        $order = $this->app->make(PlaceOrderAction::class)->execute(
            $this->makeCart([[$this->makeVariant(5000, stock: 50), 1]]),
            $this->makeOrderData($this->makeCourier()->id, ['email' => $email, 'paymentMethod' => 'cod']),
        );
        Order::query()->whereKey($order->id)->update(['user_id' => $userId]);

        return $order->fresh();
    }

    public function test_guest_orders_are_linked_when_the_email_is_verified(): void
    {
        $user = $this->customer(['email' => 'maria@example.com', 'email_verified_at' => null]);
        $mine = $this->guestOrder('maria@example.com');
        $other = $this->guestOrder('altcineva@example.com');
        $owned = $this->guestOrder('maria@example.com', $this->customer()->id);

        $this->actingAs($user)->get('/cont/comenzi')->assertDontSee($mine->number);

        $user->forceFill(['email_verified_at' => now()])->save();
        event(new Verified($user->fresh()));

        $this->assertSame($user->id, $mine->fresh()->user_id);
        $this->assertNull($other->fresh()->user_id);
        $this->assertNotSame($user->id, $owned->fresh()->user_id, 'comenzile altui cont nu se mută');

        $this->actingAs($user->fresh())->get('/cont/comenzi')->assertSee($mine->number);
    }

    public function test_unverified_accounts_do_not_get_guest_orders_on_login(): void
    {
        $user = $this->customer(['email' => 'maria@example.com', 'email_verified_at' => null]);
        $order = $this->guestOrder('maria@example.com');

        event(new Login('web', $user, false));

        $this->assertNull($order->fresh()->user_id);
    }

    public function test_verified_accounts_pick_up_new_guest_orders_on_login(): void
    {
        $user = $this->customer(['email' => 'maria@example.com']);
        $order = $this->guestOrder('maria@example.com');

        event(new Login('web', $user, false));

        $this->assertSame($user->id, $order->fresh()->user_id);
    }
}
