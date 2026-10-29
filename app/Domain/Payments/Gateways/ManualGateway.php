<?php

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\DTOs\PaymentInitiation;
use App\Domain\Payments\DTOs\PaymentNotification;
use App\Domain\Payments\DTOs\RefundResult;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Gateway "offline": înregistrează plata ca pending, fără redirect. Este folosit
 * implicit pentru card în development și poate fi confirmat manual din admin.
 */
class ManualGateway implements PaymentGateway
{
    public function provider(): string
    {
        return 'manual';
    }

    public function initiate(Order $order, Payment $payment): PaymentInitiation
    {
        return new PaymentInitiation(
            provider: $this->provider(),
            reference: 'MAN-'.$order->number,
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

    public function supportsRefunds(): bool
    {
        return true;
    }

    /**
     * Rambursarea se face în afara sistemului (virament, numerar); aici doar
     * se înregistrează, cu o referință derivată din cheia de idempotență.
     */
    public function refund(Payment $payment, int $amount, string $idempotencyKey, ?string $reason = null): RefundResult
    {
        return new RefundResult('MANREF-'.$idempotencyKey, RefundStatus::SUCCEEDED);
    }
}
