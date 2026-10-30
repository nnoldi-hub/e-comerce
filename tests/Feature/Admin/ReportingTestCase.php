<?php

namespace Tests\Feature\Admin;

use App\Domain\Checkout\Actions\PlaceOrderAction;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Pricing\PricingTestCase;

abstract class ReportingTestCase extends PricingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function staff(string $role): User
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $user->assignRole($role);
        $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

        return $user->refresh();
    }

    /**
     * Plasează o comandă prin fluxul real, apoi îi fixează statusul și data.
     */
    protected function orderAt(
        CarbonInterface|string $placedAt,
        OrderStatus $status,
        ?ProductVariant $variant = null,
        int $quantity = 1,
        string $method = 'card',
    ): Order {
        $variant ??= $this->makeVariant(10000, stock: 500);
        $cart = $this->makeCart([[$variant, $quantity]]);

        $order = $this->app->make(PlaceOrderAction::class)->execute(
            $cart,
            $this->makeOrderData($this->makeCourier()->id, ['paymentMethod' => $method]),
        );

        Order::query()->whereKey($order->id)->update([
            'status' => $status->value,
            'placed_at' => $placedAt,
        ]);

        return $order->fresh();
    }
}
