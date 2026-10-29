<?php

namespace App\Console\Commands;

use App\Domain\Orders\Exceptions\InvalidOrderTransitionException;
use App\Domain\Shipping\Actions\MarkShipmentDeliveredAction;
use App\Domain\Shipping\Exceptions\ShipmentException;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Console\Command;

class DeliverOrder extends Command
{
    protected $signature = 'orders:deliver {number : Numărul comenzii}';

    protected $description = 'Marchează comanda ca livrată (la ramburs, plata devine încasată)';

    public function handle(MarkShipmentDeliveredAction $deliver): int
    {
        $order = Order::query()->where('number', $this->argument('number'))->first();

        if ($order === null) {
            $this->error('Comanda nu există.');

            return self::FAILURE;
        }

        $shipment = Shipment::query()->where('order_id', $order->id)->latest('id')->first();

        if ($shipment === null) {
            $this->error('Comanda nu are nicio expediere.');

            return self::FAILURE;
        }

        try {
            $deliver->execute($shipment);
        } catch (ShipmentException|InvalidOrderTransitionException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Comanda {$order->number} marcată ca livrată.");

        return self::SUCCESS;
    }
}
