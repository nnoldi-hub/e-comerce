<?php

namespace App\Domain\Orders\Events;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Se publică abia după commit-ul tranzacției care a schimbat statusul: dacă
 * tranzacția este anulată, nimeni nu primește un email despre o schimbare
 * care nu a avut loc.
 */
class OrderStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly OrderStatus $from,
        public readonly OrderStatus $to,
        public readonly ?string $comment = null,
        public readonly ?int $changedBy = null,
    ) {}
}
