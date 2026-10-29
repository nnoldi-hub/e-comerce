<?php

namespace App\Listeners;

use App\Domain\Checkout\Events\OrderPlaced;
use App\Mail\OrderConfirmationMail;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendOrderConfirmation
{
    public function handle(OrderPlaced $event): void
    {
        $order = $event->order;
        $order->loadMissing('items');

        try {
            Mail::to($order->email)->send(new OrderConfirmationMail($order));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
