<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;

final readonly class Money
{
    public function __construct(
        private int $amount,
        private string $currency = 'RON',
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException('Suma nu poate fi negativă.');
        }

        if (strlen($currency) !== 3) {
            throw new InvalidArgumentException('Moneda trebuie să aibă exact 3 caractere.');
        }
    }

    public static function from(int $amount, string $currency = 'RON'): self
    {
        return new self($amount, $currency);
    }

    public static function zero(string $currency = 'RON'): self
    {
        return new self(0, $currency);
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        if ($other->amount > $this->amount) {
            throw new InvalidArgumentException('Rezultatul scăderii nu poate fi negativ.');
        }

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multiply(int $multiplier): self
    {
        if ($multiplier < 0) {
            throw new InvalidArgumentException('Multiplicatorul nu poate fi negativ.');
        }

        return new self($this->amount * $multiplier, $this->currency);
    }

    /**
     * Calculează un procent exprimat în puncte de bază (1900 = 19%),
     * cu rotunjire half-up la unitatea minoră.
     */
    public function percentage(int $basisPoints): self
    {
        if ($basisPoints < 0) {
            throw new InvalidArgumentException('Punctele de bază nu pot fi negative.');
        }

        $product = $this->amount * $basisPoints;
        $result = intdiv($product, 10000);

        if (($product % 10000) >= 5000) {
            $result++;
        }

        return new self($result, $this->currency);
    }

    public function min(self $other): self
    {
        $this->assertSameCurrency($other);

        return $this->amount <= $other->amount ? $this : $other;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount > $other->amount;
    }

    public function lessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount < $other->amount;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Monedele trebuie să fie identice.');
        }
    }
}
