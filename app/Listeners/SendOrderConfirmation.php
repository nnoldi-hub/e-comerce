<?php

namespace App\Listeners;

use App\Domain\Checkout\Events\OrderPlaced;
use App\Listeners\Concerns\DeliversMail;
use App\Mail\OrderConfirmationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendOrderConfirmation implements ShouldQueue
{
    use DeliversMail;
    use InteractsWithQueue;

    /** Job-ul se pune pe coadă abia după commit-ul tranzacției curente. */
    public bool $afterCommit = true;

    public int $tries = 3;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(OrderPlaced $event): void
    {
        $order = $event->order->fresh(['items']);

        if ($order === null) {
            return;
        }

        $this->deliver(fn () => Mail::to($order->email)->send(new OrderConfirmationMail($order)));
    }

    public function failed(OrderPlaced $event, Throwable $exception): void
    {
        report($exception);
    }
}
