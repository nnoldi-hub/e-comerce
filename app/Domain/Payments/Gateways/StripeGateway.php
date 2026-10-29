<?php

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\DTOs\PaymentInitiation;
use App\Domain\Payments\DTOs\PaymentNotification;
use App\Domain\Payments\DTOs\RefundResult;
use App\Domain\Payments\Exceptions\PaymentException;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use JsonException;

/**
 * Integrare Stripe Checkout prin HTTP, fără SDK. Webhook-ul este verificat cu
 * semnătura Stripe (HMAC SHA-256 peste "timestamp.payload").
 *
 * Sesiunea expiră la 31 de minute (Stripe acceptă între 30 de minute și 24 de
 * ore); rezervarea de stoc pentru card (config/shop.php) este mai lungă, ca o
 * plată să nu poată ajunge după anularea comenzii.
 */
class StripeGateway implements PaymentGateway
{
    private const API_BASE = 'https://api.stripe.com/v1';

    private const SESSION_MINUTES = 31;

    public function __construct(
        private readonly string $secret = '',
        private readonly string $webhookSecret = '',
        private readonly int $tolerance = 300,
    ) {}

    public function provider(): string
    {
        return 'stripe';
    }

    public function initiate(Order $order, Payment $payment): PaymentInitiation
    {
        if ($this->secret === '') {
            throw new PaymentException('Cheia secretă Stripe nu este configurată.');
        }

        try {
            $response = Http::withToken($this->secret)
                ->withHeaders(['Idempotency-Key' => 'ecom-'.$order->number.'-'.$payment->id])
                ->timeout(15)
                ->connectTimeout(5)
                ->asForm()
                ->post(self::API_BASE.'/checkout/sessions', [
                    'mode' => 'payment',
                    'client_reference_id' => $order->number,
                    'customer_email' => $order->email,
                    'success_url' => route('checkout.success').'?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => route('checkout.index'),
                    'expires_at' => now()->addMinutes(self::SESSION_MINUTES)->timestamp,
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
        } catch (ConnectionException $exception) {
            throw new PaymentException('Stripe nu a răspuns la timp: '.$exception->getMessage(), 0, $exception);
        }

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

    public function supportsRefunds(): bool
    {
        return true;
    }

    /**
     * Rambursează prin PaymentIntent-ul sesiunii Checkout. Referința plății
     * este id-ul sesiunii, deci PaymentIntent-ul se citește de la Stripe.
     * Statusul `pending` (ex. metode asincrone) rămâne pending local.
     */
    public function refund(Payment $payment, int $amount, string $idempotencyKey, ?string $reason = null): RefundResult
    {
        if ($this->secret === '') {
            throw new PaymentException('Cheia secretă Stripe nu este configurată.');
        }

        $reference = (string) $payment->provider_reference;

        try {
            $session = $this->decode(
                Http::withToken($this->secret)
                    ->timeout(15)
                    ->connectTimeout(5)
                    ->get(self::API_BASE.'/checkout/sessions/'.$reference)
            );

            $intent = $session['payment_intent'] ?? null;

            if (! is_string($intent) || $intent === '') {
                throw new PaymentException('Sesiunea Stripe nu are un PaymentIntent de rambursat.');
            }

            $data = $this->decode(
                Http::withToken($this->secret)
                    ->withHeaders(['Idempotency-Key' => $idempotencyKey])
                    ->timeout(15)
                    ->connectTimeout(5)
                    ->asForm()
                    ->post(self::API_BASE.'/refunds', array_filter([
                        'payment_intent' => $intent,
                        'amount' => $amount,
                        'metadata' => array_filter([
                            'order_id' => (string) $payment->order_id,
                            'reason' => $reason,
                        ]),
                    ]))
            );
        } catch (ConnectionException $exception) {
            throw new PaymentException('Stripe nu a răspuns la timp: '.$exception->getMessage(), 0, $exception);
        }

        $id = $data['id'] ?? null;

        if (! is_string($id) || $id === '') {
            throw new PaymentException('Răspuns invalid de la Stripe: lipsește id-ul rambursării.');
        }

        $status = match ($data['status'] ?? null) {
            'succeeded' => RefundStatus::SUCCEEDED,
            'failed', 'canceled' => RefundStatus::FAILED,
            default => RefundStatus::PENDING,
        };

        return new RefundResult($id, $status, ['refund' => $data]);
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

    /**
     * Traduce evenimentele Checkout în rezultate de plată:
     *  - completed + payment_status paid/no_payment_required -> PAID;
     *  - completed + unpaid (metode asincrone: debit direct, transfer) -> PENDING;
     *  - async_payment_succeeded -> PAID;
     *  - async_payment_failed, expired -> FAILED.
     * Orice alt eveniment este ignorat.
     */
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

        $status = match ($type) {
            'checkout.session.completed' => $this->completedStatus($object),
            'checkout.session.async_payment_succeeded' => PaymentStatus::PAID,
            'checkout.session.async_payment_failed', 'checkout.session.expired' => PaymentStatus::FAILED,
            default => null,
        };

        if ($status === null) {
            return null;
        }

        $reference = $object['id'] ?? null;

        if (! is_string($reference) || $reference === '') {
            return null;
        }

        return new PaymentNotification(
            provider: $this->provider(),
            reference: $reference,
            status: $status,
            amount: isset($object['amount_total']) ? (int) $object['amount_total'] : null,
            currency: isset($object['currency']) ? strtoupper((string) $object['currency']) : null,
            eventId: isset($event['id']) ? (string) $event['id'] : null,
            type: $type,
            payload: $event,
        );
    }

    /**
     * @param  array<mixed>  $object
     */
    private function completedStatus(array $object): PaymentStatus
    {
        $paymentStatus = $object['payment_status'] ?? null;

        return in_array($paymentStatus, ['paid', 'no_payment_required'], true)
            ? PaymentStatus::PAID
            : PaymentStatus::PENDING;
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
