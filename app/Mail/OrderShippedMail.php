<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderShippedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly Shipment $shipment,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Comanda {$this->order->number} a fost expediată");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order-shipped');
    }
}
