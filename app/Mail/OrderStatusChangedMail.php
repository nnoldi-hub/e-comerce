<?php

namespace App\Mail;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OrderStatusChangedMail extends Mailable
{
    public function __construct(
        public readonly Order $order,
        public readonly OrderStatus $previousStatus,
        public readonly OrderStatus $newStatus,
        public readonly ?string $comment = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Comanda '.$this->order->number.': '.$this->newStatus->label(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.orders.status-changed',
            with: [
                'order' => $this->order,
                'from' => $this->previousStatus,
                'to' => $this->newStatus,
                'comment' => $this->comment,
            ],
        );
    }
}
