<?php

namespace App\Listeners;

use App\Domain\Orders\Events\OrderStatusChanged;
use App\Enums\OrderStatus;
use App\Mail\OrderStatusChangedMail;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendOrderStatusNotification
{
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

    public function handle(OrderStatusChanged $event): void
    {
        if (! in_array($event->to, self::NOTIFIABLE, true)) {
            return;
        }

        try {
            Mail::to($event->order->email)->send(new OrderStatusChangedMail(
                $event->order,
                $event->from,
                $event->to,
                $event->comment,
            ));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
