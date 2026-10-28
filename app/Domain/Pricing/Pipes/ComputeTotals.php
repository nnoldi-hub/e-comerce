<?php

namespace App\Domain\Pricing\Pipes;

use App\Domain\Pricing\PricingContext;
use Closure;

class ComputeTotals
{
    public function handle(PricingContext $ctx, Closure $next): mixed
    {
        $net = $ctx->subtotal->subtract($ctx->discount)->add($ctx->shipping);

        // Cu taxă inclusă, taxa este doar informativă (deja în preț).
        $ctx->grandTotal = $ctx->taxInclusive ? $net : $net->add($ctx->tax);

        return $next($ctx);
    }
}
