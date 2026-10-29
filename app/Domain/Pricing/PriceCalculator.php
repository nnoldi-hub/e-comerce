<?php

namespace App\Domain\Pricing;

use App\Domain\Pricing\DTOs\CartTotals;
use App\Domain\Pricing\DTOs\PricingInput;
use App\Domain\Pricing\Pipes\ApplyCoupon;
use App\Domain\Pricing\Pipes\CalculateShipping;
use App\Domain\Pricing\Pipes\CalculateTax;
use App\Domain\Pricing\Pipes\CartPricing;
use App\Domain\Pricing\Pipes\ComputeTotals;
use App\Models\Cart;
use Illuminate\Contracts\Container\Container;
use Illuminate\Pipeline\Pipeline;

/**
 * Singura sursă de adevăr pentru prețuri: folosită de coș (afișare),
 * de checkout (validare) și de crearea comenzii.
 */
class PriceCalculator
{
    /** @var list<class-string> */
    private const PIPES = [
        CartPricing::class,
        ApplyCoupon::class,
        CalculateShipping::class,
        CalculateTax::class,
        ComputeTotals::class,
    ];

    public function __construct(private readonly Container $container) {}

    public function calculate(Cart $cart, ?PricingInput $input = null): CartTotals
    {
        $context = new PricingContext($cart, $input ?? new PricingInput);

        return (new Pipeline($this->container))
            ->send($context)
            ->through(self::PIPES)
            ->then(fn (PricingContext $ctx): CartTotals => $ctx->toTotals());
    }
}
