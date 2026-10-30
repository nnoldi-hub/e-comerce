<?php

namespace App\Listeners;

use App\Domain\Shipping\Events\OrderShipped;
use App\Listeners\Concerns\DeliversMail;
use App\Mail\OrderShippedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendShipmentNotification implements ShouldQueue
{
    use DeliversMail;
    use InteractsWithQueue;

    public bool $afterCommit = true;

    public int $tries = 3;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(OrderShipped $event): void
    {
        $order = $event->order->fresh();
        $shipment = $event->shipment->fresh();

        if ($order === null || $shipment === null) {
            return;
        }

        $this->deliver(fn () => Mail::to($order->email)->send(new OrderShippedMail($order, $shipment)));
    }

    public function failed(OrderShipped $event, Throwable $exception): void
    {
        report($exception);
    }
}
