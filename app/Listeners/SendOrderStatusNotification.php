<?php

namespace App\Listeners;

use App\Domain\Orders\Events\OrderStatusChanged;
use App\Enums\OrderStatus;
use App\Listeners\Concerns\DeliversMail;
use App\Mail\OrderStatusChangedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendOrderStatusNotification implements ShouldQueue
{
    use DeliversMail;
    use InteractsWithQueue;

    public bool $afterCommit = true;

    public int $tries = 3;

    /** Statusurile care merită un email către client. */
    private const NOTIFIABLE = [
        OrderStatus::PAID,
        OrderStatus::PROCESSING,
        OrderStatus::SHIPPED,
        OrderStatus::DELIVERED,
        OrderStatus::COMPLETED,
        OrderStatus::CANCELLED,
        OrderStatus::RETURNED,
        OrderStatus::REFUNDED,
    ];

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    /**
     * Evită punerea pe coadă a unui job care nu va trimite nimic.
     */
    public function shouldQueue(OrderStatusChanged $event): bool
    {
        return in_array($event->to, self::NOTIFIABLE, true);
    }

    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order->fresh();

        if ($order === null) {
            return;
        }

        $this->deliver(fn () => Mail::to($order->email)->send(new OrderStatusChangedMail(
            $order,
            $event->from,
            $event->to,
            $event->comment,
        )));
    }

    public function failed(OrderStatusChanged $event, Throwable $exception): void
    {
        report($exception);
    }
}
