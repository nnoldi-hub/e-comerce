<?php

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\DTOs\PaymentInitiation;
use App\Domain\Payments\DTOs\PaymentNotification;
use App\Domain\Payments\Exceptions\PaymentException;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use JsonException;

/**
 * Integrare Stripe Checkout prin HTTP, fără SDK. Webhook-ul este verificat cu
 * semnătura Stripe (HMAC SHA-256 peste "timestamp.payload").
 */
class StripeGateway implements PaymentGateway
{
    private const API_BASE = 'https://api.stripe.com/v1';

    public function __construct(
        private readonly string $secret = '',
        private readonly string $webhookSecret = '',
        private readonly int $tolerance = 300,
    ) {}

    public function provider(): string
    {
        return 'stripe';
    }

    public function initiate(Order $order): PaymentInitiation
    {
        if ($this->secret === '') {
            throw new PaymentException('Cheia secretă Stripe nu este configurată.');
        }

        $response = Http::withToken($this->secret)
            ->asForm()
            ->post(self::API_BASE.'/checkout/sessions', [
                'mode' => 'payment',
                'client_reference_id' => $order->number,
                'customer_email' => $order->email,
                'success_url' => route('checkout.success').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('checkout.index'),
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower($order->currency),
                        'product_data' => ['name' => 'Comanda '.$order->number],
                        'unit_amount' => $order->grand_total->amount(),
                    ],
                    'quantity' => 1,
                ]],
                'metadata' => [
                    'order_id' => (string) $order->id,
                    'order_number' => $order->number,
                ],
            ]);

        $session = $this->decode($response);

        $id = $session['id'] ?? null;

        if (! is_string($id) || $id === '') {
            throw new PaymentException('Răspuns invalid de la Stripe: lipsește id-ul sesiunii.');
        }

        $url = $session['url'] ?? null;

        return new PaymentInitiation(
            provider: $this->provider(),
            reference: $id,
            status: PaymentStatus::PENDING,
            redirectUrl: is_string($url) ? $url : null,
            payload: ['session' => $session],
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        if ($this->webhookSecret === '') {
            return false;
        }

        $header = (string) $request->header('Stripe-Signature', '');
        $parts = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            $parts[$key][] = $value;
        }

        $timestamp = $parts['t'][0] ?? null;

        if ($timestamp === null || ! ctype_digit((string) $timestamp)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > $this->tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $this->webhookSecret);

        foreach ($parts['v1'] ?? [] as $signature) {
            if (is_string($signature) && hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    public function parseWebhook(Request $request): ?PaymentNotification
    {
        try {
            $event = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($event)) {
            return null;
        }

        $type = $event['type'] ?? null;
        $data = $event['data'] ?? null;
        $object = is_array($data) ? ($data['object'] ?? null) : null;

        if (! is_string($type) || ! is_array($object)) {
            return null;
        }

        if (! in_array($type, [
            'checkout.session.completed',
            'checkout.session.async_payment_failed',
            'checkout.session.expired',
        ], true)) {
            return null;
        }

        $reference = $object['id'] ?? null;

        if (! is_string($reference) || $reference === '') {
            return null;
        }

        $paid = $type === 'checkout.session.completed';

        return new PaymentNotification(
            provider: $this->provider(),
            reference: $reference,
            status: $paid ? PaymentStatus::PAID : PaymentStatus::FAILED,
            amount: isset($object['amount_total']) ? (int) $object['amount_total'] : null,
            currency: isset($object['currency']) ? strtoupper((string) $object['currency']) : null,
            eventId: isset($event['id']) ? (string) $event['id'] : null,
            type: $type,
            payload: $event,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        $body = $response->json();

        if (! $response->successful() || ! is_array($body)) {
            $message = is_array($body) && isset($body['error']['message'])
                ? (string) $body['error']['message']
                : 'Status HTTP '.$response->status();

            throw new PaymentException('Stripe a respins cererea: '.$message);
        }

        return $body;
    }
}
