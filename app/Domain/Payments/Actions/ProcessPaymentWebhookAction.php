<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\DTOs\PaymentNotification;
use App\Domain\Payments\Exceptions\InvalidPaymentNotificationException;
use App\Models\Payment;
use App\Models\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Procesează un webhook o singură dată. Evenimentul providerului este înregistrat
 * în `webhook_events`, astfel încât retrimiterile să fie ignorate.
 */
class ProcessPaymentWebhookAction
{
    public function __construct(private readonly ApplyPaymentNotificationAction $apply) {}

    public function execute(PaymentNotification $notification): void
    {
        if ($notification->eventId === null) {
            $this->process($notification);

            return;
        }

        try {
            $event = WebhookEvent::firstOrCreate(
                [
                    'provider' => $notification->provider,
                    'event_id' => $notification->eventId,
                ],
                [
                    'type' => $notification->type,
                    'payload' => $notification->payload,
                ],
            );
        } catch (UniqueConstraintViolationException) {
            $event = WebhookEvent::query()
                ->where('provider', $notification->provider)
                ->where('event_id', $notification->eventId)
                ->firstOrFail();
        }

        if ($event->processed_at !== null) {
            return;
        }

        DB::transaction(function () use ($event, $notification) {
            $locked = WebhookEvent::query()->lockForUpdate()->findOrFail($event->id);

            if ($locked->processed_at !== null) {
                return;
            }

            $this->process($notification);

            $locked->update(['processed_at' => now()]);
        });
    }

    private function process(PaymentNotification $notification): void
    {
        $payment = Payment::query()
            ->where('provider', $notification->provider)
            ->where('provider_reference', $notification->reference)
            ->first();

        if ($payment === null) {
            throw new InvalidPaymentNotificationException(
                "Plata cu referința '{$notification->reference}' nu există."
            );
        }

        $this->apply->execute($payment, $notification);
    }
}
