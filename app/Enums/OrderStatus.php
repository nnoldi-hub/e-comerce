<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case AWAITING_PAYMENT = 'awaiting_payment';
    case PAID = 'paid';
    case PROCESSING = 'processing';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case RETURNED = 'returned';
    case REFUNDED = 'refunded';

    /**
     * Tranzițiile permise din fiecare status. Fluxul este intenționat strict:
     * o comandă nu poate sări peste plata/livrarea confirmată.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PENDING => [self::AWAITING_PAYMENT, self::PAID, self::PROCESSING, self::CANCELLED],
            self::AWAITING_PAYMENT => [self::PAID, self::CANCELLED],
            self::PAID => [self::PROCESSING, self::SHIPPED, self::CANCELLED, self::REFUNDED],
            self::PROCESSING => [self::SHIPPED, self::CANCELLED, self::REFUNDED],
            self::SHIPPED => [self::DELIVERED, self::RETURNED],
            self::DELIVERED => [self::COMPLETED, self::RETURNED, self::REFUNDED],
            self::COMPLETED => [self::RETURNED, self::REFUNDED],
            self::CANCELLED => [self::REFUNDED],
            self::RETURNED => [self::REFUNDED],
            self::REFUNDED => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'În așteptare',
            self::AWAITING_PAYMENT => 'Așteaptă plata',
            self::PAID => 'Plătită',
            self::PROCESSING => 'În pregătire',
            self::SHIPPED => 'Expediată',
            self::DELIVERED => 'Livrată',
            self::COMPLETED => 'Finalizată',
            self::CANCELLED => 'Anulată',
            self::RETURNED => 'Returnată',
            self::REFUNDED => 'Rambursată',
        };
    }
}
