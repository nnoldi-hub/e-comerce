<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\DTOs\PaymentNotification;
use App\Domain\Payments\DTOs\RefundNotification;
use App\Domain\Payments\Exceptions\InvalidPaymentNotificationException;
use App\Domain\Payments\Exceptions\PaymentNotFoundException;
use App\Models\Payment;
use App\Models\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Procesează un webhook o singură dată. Evenimentul providerului este înregistrat
 * în `webhook_events`, astfel încât retrimiterile să fie ignorate.
 *
 * Rezultat:
 *  - PROCESSED: aplicat acum;
 *  - DUPLICATE: fusese deja aplicat;
 *  - REJECTED: eroare permanentă (sumă greșită, plată necunoscută după termen);
 *    evenimentul rămâne `failed` cu motivul, iar providerul primește 202 ca să
 *    nu mai retrimită.
 *
 * O plată negăsită este tratată ca posibilă cursă (referința se salvează abia
 * după răspunsul providerului): se aruncă PaymentNotFoundException, iar
 * providerul reîncearcă, dar doar în primele minute.
 */
class ProcessPaymentWebhookAction
{
    public const PROCESSED = 'processed';

    public const DUPLICATE = 'duplicate';

    public const REJECTED = 'rejected';

    /** Cât timp o plată negăsită mai este considerată „încă nu a ajuns". */
    private const NOT_FOUND_GRACE_MINUTES = 10;

    public function __construct(
        private readonly ApplyPaymentNotificationAction $apply,
        private readonly ApplyRefundNotificationAction $applyRefund,
    ) {}

    /**
     * @throws PaymentNotFoundException când providerul trebuie să reîncerce
     */
    public function execute(PaymentNotification|RefundNotification $notification): string
    {
        $event = $this->recordEvent($notification);

        if ($event !== null) {
            if ($event->processed_at !== null) {
                return self::DUPLICATE;
            }

            WebhookEvent::query()->whereKey($event->id)->increment('attempts');
        }

        try {
            return DB::transaction(function () use ($event, $notification): string {
                $locked = null;

                if ($event !== null) {
                    $locked = WebhookEvent::query()->lockForUpdate()->findOrFail($event->id);

                    if ($locked->processed_at !== null) {
                        return self::DUPLICATE;
                    }
                }

                $this->process($notification);

                $locked?->update([
                    'status' => WebhookEvent::STATUS_PROCESSED,
                    'error' => null,
                    'processed_at' => now(),
                ]);

                return self::PROCESSED;
            });
        } catch (PaymentNotFoundException $exception) {
            if ($event !== null && $event->created_at !== null
                && $event->created_at->gt(now()->subMinutes(self::NOT_FOUND_GRACE_MINUTES))) {
                $this->recordError($event, WebhookEvent::STATUS_PENDING, $exception->getMessage());

                throw $exception;
            }

            return $this->reject($event, $exception);
        } catch (InvalidPaymentNotificationException $exception) {
            return $this->reject($event, $exception);
        }
    }

    private function recordEvent(PaymentNotification|RefundNotification $notification): ?WebhookEvent
    {
        if ($notification->eventId === null) {
            return null;
        }

        try {
            return WebhookEvent::firstOrCreate(
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
            return WebhookEvent::query()
                ->where('provider', $notification->provider)
                ->where('event_id', $notification->eventId)
                ->firstOrFail();
        }
    }

    private function process(PaymentNotification|RefundNotification $notification): void
    {
        if ($notification instanceof RefundNotification) {
            $payment = $this->paymentForIntent($notification);

            if ($payment === null) {
                throw new PaymentNotFoundException(
                    "Plata cu PaymentIntent '{$notification->intent}' nu există."
                );
            }

            $this->applyRefund->execute($payment, $notification);

            return;
        }

        $payment = Payment::query()
            ->where('provider', $notification->provider)
            ->where('provider_reference', $notification->reference)
            ->first();

        if ($payment === null) {
            throw new PaymentNotFoundException(
                "Plata cu referința '{$notification->reference}' nu există."
            );
        }

        $this->apply->execute($payment, $notification);
    }

    /**
     * Plățile confirmate înainte de coloana `provider_intent` au PaymentIntent-ul
     * doar în payload-ul evenimentului; se caută și acolo.
     */
    private function paymentForIntent(RefundNotification $notification): ?Payment
    {
        return Payment::query()
            ->where('provider', $notification->provider)
            ->where('provider_intent', $notification->intent)
            ->first()
            ?? Payment::query()
                ->where('provider', $notification->provider)
                ->where('payload->data->object->payment_intent', $notification->intent)
                ->first();
    }

    private function reject(?WebhookEvent $event, InvalidPaymentNotificationException $exception): string
    {
        report($exception);

        if ($event !== null) {
            $this->recordError($event, WebhookEvent::STATUS_FAILED, $exception->getMessage());
        }

        return self::REJECTED;
    }

    private function recordError(WebhookEvent $event, string $status, string $message): void
    {
        // În afara tranzacției eșuate (a fost deja anulată), ca urma să rămână.
        WebhookEvent::query()->whereKey($event->id)->update([
            'status' => $status,
            'error' => Str::limit($message, 1000),
        ]);
    }
}
