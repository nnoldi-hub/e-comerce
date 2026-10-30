<?php

namespace App\Domain\Shipping\Actions;

use App\Domain\Orders\Actions\TransitionOrderStatusAction;
use App\Domain\Shipping\Events\OrderShipped;
use App\Domain\Shipping\Exceptions\ShipmentException;
use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Expediază o comandă: creează expedierea (curier, AWB, link de tracking),
 * mută comanda în `shipped` prin TransitionOrderStatusAction (care scade
 * stocul o singură dată) și anunță clientul.
 *
 * O comandă are o singură expediere (expedierile parțiale nu sunt în scop).
 * Comenzile cu ramburs încă `pending` trec mai întâi prin `processing`.
 * Totul se face într-o tranzacție: dacă scăderea de stoc sau tranziția eșuează,
 * nu rămâne nicio expediere.
 */
class ShipOrderAction
{
    public function __construct(private readonly TransitionOrderStatusAction $transitions) {}

    /**
     * @throws ShipmentException
     */
    public function execute(
        Order $order,
        string $carrier,
        ?string $trackingNumber = null,
        ?string $trackingUrl = null,
        ?int $createdBy = null,
    ): Shipment {
        $carrier = trim($carrier);

        if ($carrier === '') {
            throw new ShipmentException('Curierul este obligatoriu.', 'carrier_required');
        }

        $trackingNumber = $this->clean($trackingNumber);
        $trackingUrl = $this->clean($trackingUrl);

        return DB::transaction(function () use ($order, $carrier, $trackingNumber, $trackingUrl, $createdBy): Shipment {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (Shipment::query()->where('order_id', $locked->id)->exists()) {
                throw new ShipmentException("Comanda {$locked->number} are deja o expediere.", 'already_shipped');
            }

            $this->assertShippable($locked);

            if ($locked->status === OrderStatus::PENDING) {
                $locked = $this->transitions->execute($locked, OrderStatus::PROCESSING, $createdBy, 'Pregătită pentru expediere');
            }

            $shipment = Shipment::create([
                'order_id' => $locked->id,
                'carrier' => Str::limit($carrier, 100, ''),
                'tracking_number' => $trackingNumber !== null ? Str::limit($trackingNumber, 100, '') : null,
                'tracking_url' => $trackingUrl !== null ? Str::limit($trackingUrl, 500, '') : null,
                'status' => ShipmentStatus::SHIPPED,
                'shipped_at' => now(),
                'created_by' => $createdBy,
            ]);

            $comment = 'Expediat cu '.$carrier.($trackingNumber !== null ? ", AWB {$trackingNumber}" : '');

            $locked = $this->transitions->execute($locked, OrderStatus::SHIPPED, $createdBy, $comment);
            $locked->update(['fulfillment_status' => 'fulfilled']);

            OrderShipped::dispatch($locked, $shipment);

            return $shipment;
        });
    }

    private function assertShippable(Order $order): void
    {
        $ok = match ($order->status) {
            OrderStatus::PAID, OrderStatus::PROCESSING => true,
            // Ramburs: nu așteaptă plata înainte de expediere.
            OrderStatus::PENDING => $order->payment_method === 'cod',
            default => false,
        };

        if (! $ok) {
            throw new ShipmentException(
                "Comanda {$order->number} nu poate fi expediată din statusul „{$order->status->label()}”.",
                'not_shippable',
            );
        }
    }

    private function clean(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
