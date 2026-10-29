<?php

namespace App\Console\Commands;

use App\Domain\Payments\Actions\RefundPaymentAction;
use App\Domain\Payments\Exceptions\PaymentException;
use App\Domain\Payments\Exceptions\RefundNotAllowedException;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Console\Command;

/**
 * Rambursare manuală până apare panoul de administrare (Etapa 6).
 * Fără sumă, rambursează tot ce a rămas rambursabil din ultima plată încasată.
 */
class RefundOrder extends Command
{
    protected $signature = 'orders:refund {number : Numărul comenzii (ex. 2026-000123)}
                            {amount? : Suma în moneda comenzii, ex. 49.90 (implicit: tot ce se mai poate)}
                            {--reason= : Motivul rambursării}';

    protected $description = 'Rambursează total sau parțial plata unei comenzi';

    public function handle(RefundPaymentAction $refunds): int
    {
        $order = Order::query()->where('number', $this->argument('number'))->first();

        if ($order === null) {
            $this->error('Comanda nu există.');

            return self::FAILURE;
        }

        $payment = Payment::query()
            ->where('order_id', $order->id)
            ->whereIn('status', [PaymentStatus::PAID, PaymentStatus::PARTIALLY_REFUNDED])
            ->latest('id')
            ->first();

        if ($payment === null) {
            $this->error('Comanda nu are nicio plată încasată de rambursat.');

            return self::FAILURE;
        }

        $argument = $this->argument('amount');

        try {
            $amount = $argument !== null
                ? (int) round(((float) $argument) * 100)
                : $this->remaining($payment);

            $refund = $refunds->execute($payment, $amount, $this->option('reason'));
        } catch (RefundNotAllowedException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (PaymentException $exception) {
            $this->error('Procesatorul a refuzat rambursarea: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Rambursare #%d: %s %s, status %s.',
            $refund->id,
            number_format($refund->amount->amount() / 100, 2, '.', ''),
            $refund->currency,
            $refund->status->value,
        ));

        return self::SUCCESS;
    }

    private function remaining(Payment $payment): int
    {
        $used = (int) Refund::query()
            ->where('payment_id', $payment->id)
            ->whereIn('status', [RefundStatus::PENDING, RefundStatus::SUCCEEDED])
            ->sum('amount');

        return $payment->amount->amount() - $used;
    }
}
