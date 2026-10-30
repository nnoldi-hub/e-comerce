<?php

namespace Tests\Feature\Storefront;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Admin\ReportingTestCase;

class AccountTest extends ReportingTestCase
{
    private function customer(array $overrides = []): User
    {
        return User::factory()->create(array_merge(['is_active' => true, 'email_verified_at' => now()], $overrides))->fresh();
    }

    private function ownedOrder(User $user, OrderStatus $status = OrderStatus::PAID, string $method = 'card'): Order
    {
        $order = $this->orderAt(now()->subDay(), $status, method: $method);
        Order::query()->whereKey($order->id)->update(['user_id' => $user->id]);

        return $order->fresh();
    }

    public function test_guests_are_sent_to_login(): void
    {
        foreach (['/cont', '/cont/comenzi', '/cont/profil', '/cont/parola'] as $path) {
            $this->get($path)->assertRedirect(route('login'));
        }
    }

    public function test_the_overview_lists_the_customers_recent_orders(): void
    {
        $user = $this->customer(['name' => 'Maria Ionescu']);
        $order = $this->ownedOrder($user);

        $this->actingAs($user)->get('/cont')
            ->assertOk()
            ->assertSee('Salut, Maria Ionescu')
            ->assertSee($order->number);
    }

    public function test_the_order_list_shows_only_my_orders(): void
    {
        $me = $this->customer();
        $other = $this->customer();
        $mine = $this->ownedOrder($me);
        $theirs = $this->ownedOrder($other);

        $this->actingAs($me)->get('/cont/comenzi')
            ->assertOk()
            ->assertSee($mine->number)
            ->assertDontSee($theirs->number);
    }

    public function test_someone_elses_order_is_not_found(): void
    {
        $me = $this->customer();
        $theirs = $this->ownedOrder($this->customer());

        $this->actingAs($me)->get('/cont/comenzi/'.$theirs->number)->assertNotFound();
        $this->actingAs($me)->get('/cont/comenzi/NU-EXISTA')->assertNotFound();
    }

    public function test_guest_orders_without_an_owner_are_not_visible(): void
    {
        $me = $this->customer();
        $guestOrder = $this->orderAt(now(), OrderStatus::PAID);

        $this->actingAs($me)->get('/cont/comenzi/'.$guestOrder->number)->assertNotFound();
    }

    public function test_the_order_detail_shows_items_totals_and_history_without_internal_notes(): void
    {
        $user = $this->customer();
        $order = $this->ownedOrder($user);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => OrderStatus::PENDING,
            'to_status' => OrderStatus::PAID,
            'comment' => 'NOTA INTERNA CONFIDENTIALA',
        ]);

        $this->actingAs($user)->get('/cont/comenzi/'.$order->number)
            ->assertOk()
            ->assertSee($order->number)
            ->assertSee('Plătită')
            ->assertSee('Ion Popescu')
            ->assertSee('Istoric')
            ->assertDontSee('NOTA INTERNA CONFIDENTIALA');
    }

    public function test_tracking_details_appear_once_the_order_is_shipped(): void
    {
        $user = $this->customer();
        $order = $this->ownedOrder($user, OrderStatus::SHIPPED);

        Shipment::create([
            'order_id' => $order->id,
            'carrier' => 'Fan Courier',
            'tracking_number' => 'AWB-777',
            'tracking_url' => 'https://track.test/AWB-777',
            'status' => 'shipped',
            'shipped_at' => now(),
        ]);

        $this->actingAs($user)->get('/cont/comenzi/'.$order->number)
            ->assertSee('Fan Courier')
            ->assertSee('AWB-777')
            ->assertSee('https://track.test/AWB-777', false);
    }

    public function test_an_unpaid_card_order_offers_a_signed_pay_link(): void
    {
        $user = $this->customer();
        $order = $this->ownedOrder($user, OrderStatus::AWAITING_PAYMENT);

        $this->actingAs($user)->get('/cont/comenzi/'.$order->number)
            ->assertSee('Plătește acum')
            ->assertSee('checkout/pay/'.$order->number, false)
            ->assertSee('signature=', false);

        $paid = $this->ownedOrder($user, OrderStatus::PAID);

        $this->actingAs($user)->get('/cont/comenzi/'.$paid->number)->assertDontSee('Plătește acum');
    }

    public function test_the_profile_can_be_updated_with_a_phone_number(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->put('/user/profile-information', [
            'name' => 'Nume Nou',
            'email' => $user->email,
            'phone' => '0712 345 678',
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('Nume Nou', $user->name);
        $this->assertSame('0712 345 678', $user->phone);
        $this->assertNotNull($user->email_verified_at);

        $this->actingAs($user)->put('/user/profile-information', [
            'name' => 'Nume Nou', 'email' => $user->email, 'phone' => 'abc',
        ])->assertSessionHasErrorsIn('updateProfileInformation', 'phone');
    }

    public function test_changing_the_email_requires_a_new_verification(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->put('/user/profile-information', [
            'name' => $user->name, 'email' => 'nou@example.com',
        ]);

        $user->refresh();
        $this->assertSame('nou@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_the_password_needs_the_current_one(): void
    {
        $user = $this->customer(['password' => Hash::make('parola-veche-1')]);

        $this->actingAs($user)->put('/user/password', [
            'current_password' => 'gresita',
            'password' => 'Parola-Noua-123!',
            'password_confirmation' => 'Parola-Noua-123!',
        ])->assertSessionHasErrorsIn('updatePassword', 'current_password');

        $this->assertTrue(Hash::check('parola-veche-1', $user->fresh()->password));

        $this->actingAs($user)->put('/user/password', [
            'current_password' => 'parola-veche-1',
            'password' => 'Parola-Noua-123!',
            'password_confirmation' => 'Parola-Noua-123!',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('Parola-Noua-123!', $user->fresh()->password));
    }

    public function test_the_security_page_requires_a_recent_password_confirmation(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->get('/cont/securitate')->assertRedirect(route('password.confirm'));

        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
            ->get('/cont/securitate')
            ->assertOk()
            ->assertSee('Activează verificarea în doi pași');
    }

    public function test_two_factor_setup_shows_the_qr_code_and_the_confirmation_form(): void
    {
        $user = $this->customer();
        $session = ['auth.password_confirmed_at' => time()];

        $this->actingAs($user)->withSession($session)->post('/user/two-factor-authentication')->assertRedirect();

        $this->assertNotNull($user->fresh()->two_factor_secret);

        $this->actingAs($user->fresh())->withSession($session)->get('/cont/securitate')
            ->assertOk()
            ->assertSee('<svg', false)
            ->assertSee('Confirmă și activează')
            ->assertSee('confirmed-two-factor-authentication', false);
    }

    public function test_deactivated_customers_are_logged_out(): void
    {
        $user = $this->customer(['is_active' => false]);

        $this->actingAs($user)->get('/cont')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_staff_are_sent_to_the_admin_panel(): void
    {
        $staff = $this->staff('Manager Inventar');

        $this->actingAs($staff)->get('/cont')->assertRedirect('/admin');
    }

    public function test_unverified_customers_see_a_confirmation_notice(): void
    {
        $user = $this->customer(['email_verified_at' => null]);

        $this->actingAs($user)->get('/cont')
            ->assertSee('nu este confirmată')
            ->assertSee('email/verification-notification', false);
    }

    public function test_the_header_links_to_the_account_when_logged_in(): void
    {
        $this->actingAs($this->customer())->get('/')->assertSee('Contul meu');
    }

    public function test_fortify_home_goes_to_the_account(): void
    {
        $this->get('/home')->assertRedirect('/cont');
    }
}
