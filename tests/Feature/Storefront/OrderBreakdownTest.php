<?php

namespace Tests\Feature\Storefront;

use App\Domain\Checkout\Actions\PlaceOrderAction;
use App\Enums\OrderStatus;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderStatusChangedMail;
use App\Models\Order;
use App\Support\AddressFormatter;
use App\Support\OrderTotals;
use Tests\Feature\Pricing\PricingTestCase;

class OrderBreakdownTest extends PricingTestCase
{
    private function order(bool $taxInclusive = true, int $price = 10000): Order
    {
        $this->makeTax(2100, inclusive: $taxInclusive);
        $courier = $this->makeCourier();
        $cart = $this->makeCart([[$this->makeVariant($price), 1]]);

        return $this->app->make(PlaceOrderAction::class)
            ->execute($cart, $this->makeOrderData($courier->id, ['paymentMethod' => 'cod']))
            ->fresh(['items']);
    }

    /** @return array<string, array{label: string, amount: int, kind: string}> */
    private function byLabel(Order $order): array
    {
        $rows = [];
        foreach (OrderTotals::rows($order) as $row) {
            $rows[$row['label']] = $row;
        }

        return $rows;
    }

    public function test_inclusive_vat_is_shown_as_part_of_the_total(): void
    {
        $order = $this->order(taxInclusive: true);
        $rows = $this->byLabel($order);

        $this->assertSame(10000, $rows['Subtotal']['amount']);
        $this->assertSame(1999, $rows['Transport']['amount']);
        $this->assertSame('info', $rows['din care TVA']['kind']);
        $this->assertSame(11999, $rows['Total']['amount']);
        $this->assertArrayNotHasKey('Reducere', $rows);
    }

    public function test_exclusive_vat_is_an_added_line_and_free_shipping_is_marked(): void
    {
        $order = $this->order(taxInclusive: false, price: 30000);
        $rows = $this->byLabel($order);

        $this->assertSame('free', $rows['Transport']['kind']);
        $this->assertSame('normal', $rows['TVA']['kind']);
        $this->assertSame(36300, $rows['Total']['amount']);
    }

    public function test_success_page_shows_the_full_breakdown_and_address(): void
    {
        $order = $this->order();

        $this->withSession(['order_number' => $order->number])
            ->get(route('checkout.success'))
            ->assertOk()
            ->assertSee('Subtotal')
            ->assertSee('Transport')
            ->assertSee('din care TVA')
            ->assertSee('119,99 RON')
            ->assertSee('Str. Exemplu 1')
            ->assertSee('client@example.com');
    }

    public function test_confirmation_email_has_totals_and_delivery_address(): void
    {
        $html = (new OrderConfirmationMail($this->order()))->render();

        $this->assertStringContainsString('Mulțumim pentru comandă', $html);
        $this->assertStringContainsString('Subtotal', $html);
        $this->assertStringContainsString('Transport', $html);
        $this->assertStringContainsString('119,99 RON', $html);
        $this->assertStringContainsString('Ion Popescu', $html);
        $this->assertStringContainsString('Ramburs la livrare', $html);
    }

    public function test_status_email_uses_the_shared_layout(): void
    {
        $order = $this->order();
        $html = (new OrderStatusChangedMail($order, OrderStatus::PENDING, OrderStatus::PROCESSING, 'Pregătim coletul'))->render();

        $this->assertStringContainsString('În pregătire', $html);
        $this->assertStringContainsString('Pregătim coletul', $html);
        $this->assertStringContainsString('119,99 RON', $html);
        $this->assertStringContainsString('<html lang="ro">', $html);
    }

    public function test_address_formatter_orders_lines_like_a_courier_label(): void
    {
        $this->assertSame(
            ['Ion Popescu', 'Str. A 1', '010101 București', 'România'],
            AddressFormatter::lines(['first_name' => 'Ion', 'last_name' => 'Popescu', 'address_line1' => 'Str. A 1', 'city' => 'București', 'postal_code' => '010101', 'country' => 'RO']),
        );
        $this->assertSame([], AddressFormatter::lines(null));
    }
}
