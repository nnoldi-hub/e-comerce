<?php

namespace App\Domain\Shipping\Actions;

use App\Domain\Orders\Actions\TransitionOrderStatusAction;
use App\Domain\Orders\Exceptions\InvalidOrderTransitionException;
use App\Domain\Shipping\Exceptions\ShipmentException;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;

/**
 * Marchează o expediere ca livrată: comanda trece în `delivered`, iar la
 * ramburs plata se consideră încasată în momentul livrării (curierul a luat
 * banii), ca rambursările ulterioare să poată porni din `paid`.
 */
class MarkShipmentDeliveredAction
{
    public function __construct(private readonly TransitionOrderStatusAction $transitions) {}

    /**
     * @throws ShipmentException comanda nu este expediată
     * @throws InvalidOrderTransitionException tranziția comenzii a fost refuzată
     */
    public function execute(Shipment $shipment, ?int $changedBy = null): Shipment
    {
        return DB::transaction(function () use ($shipment, $changedBy): Shipment {
            $locked = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);

            if ($locked->status === ShipmentStatus::DELIVERED) {
                return $locked;
            }

            $order = Order::query()->lockForUpdate()->findOrFail($locked->order_id);

            if ($order->status !== OrderStatus::SHIPPED) {
                throw new ShipmentException(
                    "Comanda {$order->number} nu este în statusul „Expediată”.",
                    'not_shipped',
                );
            }

            $locked->update([
                'status' => ShipmentStatus::DELIVERED,
                'delivered_at' => now(),
            ]);

            $this->collectCashOnDelivery($order);

            $this->transitions->execute($order, OrderStatus::DELIVERED, $changedBy, 'Livrată');

            return $locked->refresh();
        });
    }

    private function collectCashOnDelivery(Order $order): void
    {
        if ($order->payment_method !== 'cod' || $order->payment_status !== PaymentStatus::PENDING) {
            return;
        }

        $collected = Payment::query()
            ->where('order_id', $order->id)
            ->where('provider', 'cod')
            ->where('status', PaymentStatus::PENDING)
            ->update(['status' => PaymentStatus::PAID, 'paid_at' => now()]);

        if ($collected > 0) {
            $order->update(['payment_status' => PaymentStatus::PAID]);
        }
    }
}
