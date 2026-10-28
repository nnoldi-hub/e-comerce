<?php

use App\ValueObjects\Money;

it('adună sume în aceeași monedă', function () {
    expect(Money::from(1000)->add(Money::from(250))->amount())->toBe(1250);
});

it('scade sume în aceeași monedă', function () {
    expect(Money::from(1000)->subtract(Money::from(250))->amount())->toBe(750);
});

it('înmulțește cu un întreg', function () {
    expect(Money::from(499)->multiply(3)->amount())->toBe(1497);
});

it('rotunjește procentul half-up', function (int $amount, int $bps, int $expected) {
    expect(Money::from($amount)->percentage($bps)->amount())->toBe($expected);
})->with([
    'simplu 15%' => [1000, 1500, 150],
    '19% din 999 (189,81)' => [999, 1900, 190],
    'exact 0,5 urcă' => [5, 1000, 1],
    'exact 0,5 urcă (4 x 12,5%)' => [4, 1250, 1],
    'sub 0,5 coboară' => [1, 4999, 0],
    '0,9999 urcă' => [3, 3333, 1],
    'zero' => [0, 1900, 0],
]);

it('refuză sume negative', function () {
    expect(fn () => Money::from(-1))->toThrow(InvalidArgumentException::class);
});

it('refuză scăderi care ar da rezultat negativ', function () {
    expect(fn () => Money::from(100)->subtract(Money::from(101)))->toThrow(InvalidArgumentException::class);
});

it('refuză multiplicator și puncte de bază negative', function () {
    expect(fn () => Money::from(100)->multiply(-1))->toThrow(InvalidArgumentException::class);
    expect(fn () => Money::from(100)->percentage(-1))->toThrow(InvalidArgumentException::class);
});

it('refuză monede cu lungime diferită de 3', function () {
    expect(fn () => Money::from(100, 'RO'))->toThrow(InvalidArgumentException::class);
});

it('refuză operații între monede diferite', function () {
    expect(fn () => Money::from(100, 'RON')->add(Money::from(100, 'EUR')))->toThrow(InvalidArgumentException::class);
    expect(fn () => Money::from(100, 'RON')->greaterThan(Money::from(1, 'EUR')))->toThrow(InvalidArgumentException::class);
});

it('compară și identifică zero', function () {
    expect(Money::from(100)->equals(Money::from(100)))->toBeTrue()
        ->and(Money::from(100)->equals(Money::from(100, 'EUR')))->toBeFalse()
        ->and(Money::from(100)->greaterThan(Money::from(99)))->toBeTrue()
        ->and(Money::from(99)->lessThan(Money::from(100)))->toBeTrue()
        ->and(Money::zero()->isZero())->toBeTrue()
        ->and(Money::from(100)->min(Money::from(40))->amount())->toBe(40);
});
