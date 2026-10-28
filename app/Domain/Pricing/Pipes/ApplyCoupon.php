<?php

namespace App\Domain\Pricing\Pipes;

use App\Domain\Pricing\Coupons\CouponValidator;
use App\Domain\Pricing\PricingContext;
use App\Models\Coupon;
use App\ValueObjects\Money;
use Closure;

class ApplyCoupon
{
    public function __construct(private readonly CouponValidator $validator) {}

    public function handle(PricingContext $ctx, Closure $next): mixed
    {
        $code = trim((string) $ctx->input->couponCode);

        if ($code === '') {
            return $next($ctx);
        }

        $coupon = Coupon::query()->where('code', $code)->first();
        $check = $this->validator->check($coupon, $ctx->subtotal, $ctx->input->userId, now());

        if (! $check->valid) {
            $ctx->couponError = $check->reason;

            return $next($ctx);
        }

        $ctx->coupon = $coupon;
        $currency = $ctx->currency();

        $discount = match ($coupon->type) {
            'percent' => $ctx->subtotal->percentage($coupon->value),
            'fixed' => Money::from($coupon->value, $currency),
            default => Money::zero($currency),
        };

        if ($coupon->type === 'free_shipping') {
            $ctx->freeShipping = true;
        }

        // Reducerea nu poate depăși subtotalul.
        $ctx->discount = $discount->min($ctx->subtotal);

        return $next($ctx);
    }
}
