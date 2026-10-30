<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Domain\Orders\Actions\TransitionOrderStatusAction;
use App\Domain\Orders\Exceptions\InvalidOrderTransitionException;
use App\Domain\Payments\Actions\RefundPaymentAction;
use App\Domain\Payments\Exceptions\RefundNotAllowedException;
use App\Domain\Shipping\Actions\MarkShipmentDeliveredAction;
use App\Domain\Shipping\Actions\ShipOrderAction;
use App\Domain\Shipping\Exceptions\ShipmentException;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\ShipmentStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\MinorUnits;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Acțiunile de aici doar apelează acțiunile de domeniu (singurele locuri unde
 * se schimbă statusul, stocul și banii); nu duplică nicio regulă de business.
 */
class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    /** Tranziții manuale: restul au fluxuri proprii (expediere, livrare, plată, rambursare). */
    private const MANUAL_TARGETS = [
        OrderStatus::PROCESSING,
        OrderStatus::COMPLETED,
        OrderStatus::RETURNED,
        OrderStatus::CANCELLED,
    ];

    protected function getHeaderActions(): array
    {
        return [
            $this->changeStatusAction(),
            $this->shipAction(),
            $this->deliverAction(),
            $this->refundAction(),
        ];
    }

    private function order(): Order
    {
        $record = $this->getRecord();
        assert($record instanceof Order);

        return $record;
    }

    private function userId(): ?int
    {
        $id = auth()->id();

        return $id === null ? null : (int) $id;
    }

    private function succeed(string $title): void
    {
        Notification::make()->title($title)->success()->send();

        $this->redirect(OrderResource::getUrl('view', ['record' => $this->order()]));
    }

    private function refuse(string $message): void
    {
        Notification::make()->title('Acțiunea nu a fost efectuată')->body($message)->danger()->send();
    }

    private function unexpected(Throwable $exception): void
    {
        report($exception);

        Notification::make()
            ->title('Eroare neașteptată')
            ->body('Verifică starea comenzii înainte de a încerca din nou.')
            ->danger()
            ->send();

        $this->redirect(OrderResource::getUrl('view', ['record' => $this->order()]));
    }

    // ---- Schimbare status ----

    /** @return list<OrderStatus> */
    private function manualTargets(Order $order): array
    {
        $paid = in_array($order->payment_status, [PaymentStatus::PAID, PaymentStatus::PARTIALLY_REFUNDED], true);

        return array_values(array_filter(
            $order->status->allowedTransitions(),
            fn (OrderStatus $status): bool => in_array($status, self::MANUAL_TARGETS, true)
                && ! ($status === OrderStatus::CANCELLED && $paid),
        ));
    }

    private function changeStatusAction(): Action
    {
        return Action::make('changeStatus')
            ->label('Schimbă statusul')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->visible(fn (): bool => Gate::allows('update', $this->order()) && $this->manualTargets($this->order()) !== [])
            ->modalHeading('Schimbă statusul comenzii')
            ->modalDescription('Expedierea, livrarea și rambursarea au acțiunile lor. Anularea eliberează stocul rezervat.')
            ->schema([
                Select::make('status')->label('Status nou')->native(false)->required()
                    ->options(fn (): array => collect($this->manualTargets($this->order()))
                        ->mapWithKeys(fn (OrderStatus $s): array => [$s->value => $s->label()])
                        ->all()),
                Textarea::make('comment')->label('Comentariu')->rows(2)->maxLength(500),
            ])
            ->action(function (array $data): void {
                $order = $this->order();
                Gate::authorize('update', $order);

                $comment = isset($data['comment']) && trim((string) $data['comment']) !== '' ? trim((string) $data['comment']) : null;

                try {
                    app(TransitionOrderStatusAction::class)->execute(
                        $order,
                        OrderStatus::from((string) $data['status']),
                        $this->userId(),
                        $comment,
                    );
                } catch (InvalidOrderTransitionException $e) {
                    $this->refuse($e->getMessage());

                    return;
                } catch (Throwable $e) {
                    $this->unexpected($e);

                    return;
                }

                $this->succeed('Statusul a fost actualizat.');
            });
    }

    // ---- Expediere ----

    private function canShip(Order $order): bool
    {
        if (OrderResource::shipmentOf($order) !== null) {
            return false;
        }

        return match ($order->status) {
            OrderStatus::PAID, OrderStatus::PROCESSING => true,
            OrderStatus::PENDING => $order->payment_method === 'cod',
            default => false,
        };
    }

    private function shipAction(): Action
    {
        return Action::make('ship')
            ->label('Expediază')
            ->icon('heroicon-o-truck')
            ->visible(fn (): bool => Gate::allows('update', $this->order()) && $this->canShip($this->order()))
            ->modalHeading('Expediază comanda')
            ->modalDescription('Scade stocul rezervat și îl anunță pe client.')
            ->schema([
                TextInput::make('carrier')->label('Curier')->required()->maxLength(100),
                TextInput::make('tracking_number')->label('AWB')->maxLength(100),
                TextInput::make('tracking_url')->label('Link de urmărire')->url()->maxLength(500),
            ])
            ->action(function (array $data): void {
                $order = $this->order();
                Gate::authorize('update', $order);

                try {
                    app(ShipOrderAction::class)->execute(
                        $order,
                        (string) $data['carrier'],
                        $data['tracking_number'] ?? null,
                        $data['tracking_url'] ?? null,
                        $this->userId(),
                    );
                } catch (ShipmentException|InvalidOrderTransitionException $e) {
                    $this->refuse($e->getMessage());

                    return;
                } catch (Throwable $e) {
                    $this->unexpected($e);

                    return;
                }

                $this->succeed('Comanda a fost expediată.');
            });
    }

    // ---- Livrare ----

    private function deliverAction(): Action
    {
        return Action::make('markDelivered')
            ->label('Marchează livrată')
            ->icon('heroicon-o-check-badge')
            ->visible(function (): bool {
                $order = $this->order();
                $shipment = OrderResource::shipmentOf($order);

                return Gate::allows('update', $order)
                    && $order->status === OrderStatus::SHIPPED
                    && $shipment !== null
                    && $shipment->status === ShipmentStatus::SHIPPED;
            })
            ->requiresConfirmation()
            ->modalHeading('Marchează comanda ca livrată')
            ->modalDescription(fn (): string => $this->order()->payment_method === 'cod'
                ? 'Comanda este cu ramburs: plata se consideră încasată în acest moment.'
                : 'Comanda trece în statusul „Livrată”.')
            ->action(function (): void {
                $order = $this->order();
                Gate::authorize('update', $order);

                $shipment = OrderResource::shipmentOf($order);

                if ($shipment === null) {
                    $this->refuse('Comanda nu are expediere.');

                    return;
                }

                try {
                    app(MarkShipmentDeliveredAction::class)->execute($shipment, $this->userId());
                } catch (ShipmentException|InvalidOrderTransitionException $e) {
                    $this->refuse($e->getMessage());

                    return;
                } catch (Throwable $e) {
                    $this->unexpected($e);

                    return;
                }

                $this->succeed('Comanda a fost marcată ca livrată.');
            });
    }

    // ---- Rambursare ----

    /**
     * Plățile încasate ale comenzii, cu suma încă rambursabilă (în bani).
     *
     * @return list<array{payment: Payment, remaining: int}>
     */
    private function refundablePayments(Order $order): array
    {
        $rows = [];

        $payments = Payment::query()
            ->where('order_id', $order->id)
            ->whereIn('status', [PaymentStatus::PAID, PaymentStatus::PARTIALLY_REFUNDED])
            ->orderBy('id')
            ->get();

        foreach ($payments as $payment) {
            $used = (int) Refund::query()
                ->where('payment_id', $payment->id)
                ->whereIn('status', [RefundStatus::PENDING, RefundStatus::SUCCEEDED])
                ->sum('amount');

            $remaining = $payment->amount->amount() - $used;

            if ($remaining > 0) {
                $rows[] = ['payment' => $payment, 'remaining' => $remaining];
            }
        }

        return $rows;
    }

    private function remainingFor(Order $order, mixed $paymentId): int
    {
        foreach ($this->refundablePayments($order) as $row) {
            if ($row['payment']->id === (int) $paymentId) {
                return $row['remaining'];
            }
        }

        return 0;
    }

    private function refundAction(): Action
    {
        return Action::make('refund')
            ->label('Rambursează')
            ->icon('heroicon-o-receipt-refund')
            ->color('danger')
            ->visible(fn (): bool => Gate::allows('refund', $this->order()) && $this->refundablePayments($this->order()) !== [])
            ->modalHeading('Rambursează o plată')
            ->schema([
                Select::make('payment_id')->label('Plata')->native(false)->required()->live()
                    ->options(function (): array {
                        $options = [];

                        foreach ($this->refundablePayments($this->order()) as $row) {
                            $payment = $row['payment'];
                            $options[$payment->id] = "#{$payment->id} · {$payment->provider} · rămas ".number_format($row['remaining'] / 100, 2, ',', '.').' '.$payment->currency;
                        }

                        return $options;
                    })
                    ->default(function (): ?int {
                        $rows = $this->refundablePayments($this->order());

                        return $rows === [] ? null : $rows[0]['payment']->id;
                    })
                    ->afterStateUpdated(fn ($state, $set) => $set('amount', $this->remainingFor($this->order(), $state) / 100)),
                TextInput::make('amount')->label('Sumă (RON)')->required()->numeric()->minValue(0.01)->step(0.01)
                    ->default(function (): ?float {
                        $rows = $this->refundablePayments($this->order());

                        return $rows === [] ? null : $rows[0]['remaining'] / 100;
                    })
                    ->maxValue(fn ($get): float => max(0.01, $this->remainingFor($this->order(), $get('payment_id')) / 100)),
                Textarea::make('reason')->label('Motiv')->rows(2)->maxLength(255),
            ])
            ->action(function (array $data): void {
                $order = $this->order();
                Gate::authorize('refund', $order);

                $payment = Payment::query()->where('order_id', $order->id)->find((int) $data['payment_id']);

                if ($payment === null) {
                    $this->refuse('Plata aleasă nu aparține acestei comenzi.');

                    return;
                }

                $reason = isset($data['reason']) && trim((string) $data['reason']) !== '' ? trim((string) $data['reason']) : null;

                try {
                    $refund = app(RefundPaymentAction::class)->execute(
                        $payment,
                        MinorUnits::toMinor($data['amount']) ?? 0,
                        $reason,
                        $this->userId(),
                    );
                } catch (RefundNotAllowedException $e) {
                    $this->refuse($e->getMessage());

                    return;
                } catch (Throwable $e) {
                    $this->unexpected($e);

                    return;
                }

                $this->succeed($refund->status === RefundStatus::PENDING
                    ? 'Rambursarea a fost trimisă; se confirmă de la procesatorul de plăți.'
                    : 'Rambursarea a fost efectuată.');
            });
    }
}
