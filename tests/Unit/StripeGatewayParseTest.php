<?php

namespace Tests\Unit;

use App\Domain\Payments\Gateways\StripeGateway;
use App\Enums\PaymentStatus;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StripeGatewayParseTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $object
     */
    private function request(string $type, array $object = []): Request
    {
        return Request::create('/webhooks/payments/stripe', 'POST', [], [], [], [], (string) json_encode([
            'id' => 'evt_1',
            'type' => $type,
            'data' => ['object' => array_merge(['id' => 'cs_1', 'amount_total' => 24200, 'currency' => 'ron'], $object)],
        ]));
    }

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>, 2: ?PaymentStatus}>
     */
    public static function events(): array
    {
        return [
            'completed + paid' => ['checkout.session.completed', ['payment_status' => 'paid'], PaymentStatus::PAID],
            'completed + no_payment_required' => ['checkout.session.completed', ['payment_status' => 'no_payment_required'], PaymentStatus::PAID],
            'completed + unpaid' => ['checkout.session.completed', ['payment_status' => 'unpaid'], PaymentStatus::PENDING],
            'completed fără payment_status' => ['checkout.session.completed', [], PaymentStatus::PENDING],
            'async_payment_succeeded' => ['checkout.session.async_payment_succeeded', [], PaymentStatus::PAID],
            'async_payment_failed' => ['checkout.session.async_payment_failed', [], PaymentStatus::FAILED],
            'expired' => ['checkout.session.expired', [], PaymentStatus::FAILED],
            'eveniment necunoscut' => ['payment_intent.succeeded', [], null],
            'fără referință' => ['checkout.session.completed', ['id' => '', 'payment_status' => 'paid'], null],
        ];
    }

    /**
     * @param  array<string, mixed>  $object
     */
    #[DataProvider('events')]
    public function test_it_maps_stripe_events_to_payment_results(string $type, array $object, ?PaymentStatus $expected): void
    {
        $notification = (new StripeGateway('sk', 'wh'))->parseWebhook($this->request($type, $object));

        if ($expected === null) {
            $this->assertNull($notification);

            return;
        }

        $this->assertNotNull($notification);
        $this->assertSame($expected, $notification->status);
        $this->assertSame('cs_1', $notification->reference);
        $this->assertSame(24200, $notification->amount);
        $this->assertSame('RON', $notification->currency);
        $this->assertSame('evt_1', $notification->eventId);
    }

    public function test_it_ignores_a_body_that_is_not_json(): void
    {
        $request = Request::create('/webhooks/payments/stripe', 'POST', [], [], [], [], 'nu este json');

        $this->assertNull((new StripeGateway('sk', 'wh'))->parseWebhook($request));
    }
}
