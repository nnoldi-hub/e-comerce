<?php

namespace App\Domain\Pricing\Pipes;

use App\Domain\Pricing\PricingContext;
use App\Models\TaxRate;
use Closure;

/**
 * Se aplică o singură cotă: cea activă pentru țara de livrare (cu prioritate
 * pe is_default), altfel cota implicită fără țară, altfel zero.
 * Cotele pe categorii de produse (ex. TVA redus 11%) vor necesita o clasă
 * de taxă pe produs.
 */
class CalculateTax
{
    public function handle(PricingContext $ctx, Closure $next): mixed
    {
        $country = strtoupper($ctx->input->country);

        $rate = TaxRate::query()
            ->where('is_active', true)
            ->where('country', $country)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first()
            ?? TaxRate::query()
                ->where('is_active', true)
                ->whereNull('country')
                ->where('is_default', true)
                ->first();

        if (! $rate) {
            $ctx->taxRateBps = 0;
            $ctx->taxInclusive = true;

            return $next($ctx);
        }

        // Taxa se aplică pe (subtotal - reducere + transport).
        $base = $ctx->subtotal->subtract($ctx->discount)->add($ctx->shipping);

        $ctx->taxRateBps = $rate->rate_bps;
        $ctx->taxInclusive = $rate->is_inclusive;
        $ctx->tax = $rate->is_inclusive
            ? $base->extractTax($rate->rate_bps)
            : $base->percentage($rate->rate_bps);

        return $next($ctx);
    }
}
