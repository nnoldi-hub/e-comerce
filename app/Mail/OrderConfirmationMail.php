<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OrderConfirmationMail extends Mailable
{
    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Comanda '.$this->order->number.' a fost înregistrată',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.orders.confirmation',
            with: ['order' => $this->order],
        );
    }
}
