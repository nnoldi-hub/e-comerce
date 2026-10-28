<?php

namespace App\Domain\Pricing\Pipes;

use App\Domain\Pricing\DTOs\PricedLine;
use App\Domain\Pricing\PricingContext;
use App\ValueObjects\Money;
use Closure;

/**
 * Prețurile se citesc din variantele curente, nu din unit_price_snapshot
 * al coșului (care poate fi depășit).
 */
class CartPricing
{
    public function handle(PricingContext $ctx, Closure $next): mixed
    {
        $ctx->cart->loadMissing('items.variant.product');

        $currency = $ctx->currency();
        $subtotal = Money::zero($currency);
        $lines = [];

        foreach ($ctx->cart->items as $item) {
            $variant = $item->variant;
            $unitPrice = Money::from($variant->price, $currency);
            $lineTotal = $unitPrice->multiply($item->quantity);

            $lines[] = new PricedLine(
                productVariantId: $variant->id,
                sku: $variant->sku,
                name: $variant->product?->name ?? $variant->sku,
                unitPrice: $unitPrice,
                quantity: $item->quantity,
                lineTotal: $lineTotal,
            );

            $subtotal = $subtotal->add($lineTotal);
        }

        $ctx->lines = $lines;
        $ctx->subtotal = $subtotal;

        return $next($ctx);
    }
}
