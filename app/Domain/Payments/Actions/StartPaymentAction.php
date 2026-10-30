<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\DTOs\PaymentInitiation;
use App\Domain\Payments\PaymentGatewayManager;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Throwable;

/**
 * Creează înregistrarea de plată și pornește gateway-ul ales pentru metoda
 * comenzii (card -> configurabil, ramburs -> COD).
 */
class StartPaymentAction
{
    public function __construct(private readonly PaymentGatewayManager $gateways) {}

    public function execute(Order $order): Payment
    {
        $method = $order->payment_method ?: 'card';
        $gateway = $this->gateways->forMethod($method);

        $existing = Payment::query()
            ->where('order_id', $order->id)
            ->where('provider', $gateway->provider())
            ->where('status', PaymentStatus::PENDING)
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $payment = new Payment([
            'order_id' => $order->id,
            'provider' => $gateway->provider(),
            'method' => $method,
            'amount' => $order->grand_total->amount(),
            'currency' => $order->currency,
            'status' => PaymentStatus::PENDING,
            'payload' => ['method' => $method],
        ]);
        $payment->save();

        try {
            $initiation = $gateway->initiate($order);
        } catch (Throwable $exception) {
            $payment->update([
                'status' => PaymentStatus::FAILED,
                'payload' => array_merge($payment->payload ?? [], [
                    'error' => $exception->getMessage(),
                ]),
            ]);

            throw $exception;
        }

        $payment->update([
            'provider_reference' => $initiation->reference,
            'status' => $initiation->status,
            'payload' => array_merge(
                $payment->payload ?? [],
                $initiation->payload,
                $this->payloadFor($initiation, $method),
            ),
        ]);

        return $payment->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(PaymentInitiation $initiation, string $method): array
    {
        return [
            'method' => $method,
            'gateway' => $initiation->provider,
            'redirect_url' => $initiation->redirectUrl,
        ];
    }
}
