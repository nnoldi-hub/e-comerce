<?php

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\DTOs\PaymentInitiation;
use App\Domain\Payments\DTOs\PaymentNotification;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Plata se face la livrare; nu există redirect sau webhook. Administrarea
 * marchează plata ca încasată când curierul confirmă livrarea.
 */
class CashOnDeliveryGateway implements PaymentGateway
{
    public function provider(): string
    {
        return 'cod';
    }

    public function initiate(Order $order, Payment $payment): PaymentInitiation
    {
        return new PaymentInitiation(
            provider: $this->provider(),
            reference: 'COD-'.$order->number,
            status: PaymentStatus::PENDING,
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        return false;
    }

    public function parseWebhook(Request $request): ?PaymentNotification
    {
        return null;
    }
}
