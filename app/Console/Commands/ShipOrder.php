<?php

namespace App\Console\Commands;

use App\Domain\Orders\Exceptions\InvalidOrderTransitionException;
use App\Domain\Shipping\Actions\ShipOrderAction;
use App\Domain\Shipping\Exceptions\ShipmentException;
use App\Models\Order;
use Illuminate\Console\Command;

/**
 * Expediere manuală până apare panoul de administrare (Etapa 6).
 */
class ShipOrder extends Command
{
    protected $signature = 'orders:ship {number : Numărul comenzii}
                            {carrier : Curierul (ex. "Fan Courier")}
                            {--awb= : Numărul AWB}
                            {--url= : Linkul de urmărire}';

    protected $description = 'Expediază o comandă (scade stocul și trimite emailul cu AWB)';

    public function handle(ShipOrderAction $ship): int
    {
        $order = Order::query()->where('number', $this->argument('number'))->first();

        if ($order === null) {
            $this->error('Comanda nu există.');

            return self::FAILURE;
        }

        try {
            $shipment = $ship->execute(
                $order,
                (string) $this->argument('carrier'),
                $this->option('awb'),
                $this->option('url'),
            );
        } catch (ShipmentException|InvalidOrderTransitionException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Comanda {$order->number} expediată cu {$shipment->carrier}".($shipment->tracking_number ? ", AWB {$shipment->tracking_number}" : '').'.');

        return self::SUCCESS;
    }
}
