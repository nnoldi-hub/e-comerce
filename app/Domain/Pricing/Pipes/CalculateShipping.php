<?php

namespace App\Domain\Pricing\Pipes;

use App\Domain\Pricing\PricingContext;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\ValueObjects\Money;
use Closure;

/**
 * Tariful se alege după subtotalul de după reducere. O zonă fără țări
 * definite (null sau listă goală) acoperă toate țările.
 * Erori: unavailable (metodă/zonă/țară), no_rate (niciun interval potrivit).
 */
class CalculateShipping
{
    public function handle(PricingContext $ctx, Closure $next): mixed
    {
        $methodId = $ctx->input->shippingMethodId;

        if ($methodId === null) {
            return $next($ctx);
        }

        $method = ShippingMethod::query()
            ->with('zone')
            ->where('is_active', true)
            ->find($methodId);

        $zone = $method?->zone;

        if (! $method || ! $zone || ! $zone->is_active || ! $this->zoneServes($zone, $ctx->input->country)) {
            $ctx->shippingError = 'unavailable';

            return $next($ctx);
        }

        $net = $ctx->subtotal->subtract($ctx->discount)->amount();

        $rate = $method->rates()
            ->where('min_subtotal', '<=', $net)
            ->where(fn ($query) => $query->whereNull('max_subtotal')->orWhere('max_subtotal', '>=', $net))
            ->orderByDesc('min_subtotal')
            ->first();

        if (! $rate) {
            $ctx->shippingError = 'no_rate';

            return $next($ctx);
        }

        $ctx->shippingMethodId = $method->id;
        $ctx->shipping = $ctx->freeShipping
            ? Money::zero($ctx->currency())
            : Money::from($rate->price, $ctx->currency());

        return $next($ctx);
    }

    private function zoneServes(ShippingZone $zone, string $country): bool
    {
        $countries = $zone->countries ?? [];

        if ($countries === []) {
            return true;
        }

        return in_array(strtoupper($country), array_map('strtoupper', $countries), true);
    }
}
