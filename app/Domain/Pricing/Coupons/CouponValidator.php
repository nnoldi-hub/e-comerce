<?php

namespace App\Domain\Pricing\Coupons;

use App\Models\Coupon;
use App\ValueObjects\Money;
use Carbon\CarbonInterface;

/**
 * Verificare consultativă (fără blocare). La plasarea comenzii, limitele
 * max_uses / max_uses_per_user trebuie reverificate sub lock în tranzacție.
 *
 * Motivele sunt coduri mașină: not_found, inactive, not_started, expired,
 * min_subtotal, exhausted, login_required, user_limit, invalid_value.
 */
class CouponValidator
{
    public function check(?Coupon $coupon, Money $subtotal, ?int $userId, CarbonInterface $now): CouponCheck
    {
        if ($coupon === null) {
            return CouponCheck::invalid('not_found');
        }

        if (! $coupon->is_active) {
            return CouponCheck::invalid('inactive');
        }

        if ($coupon->starts_at !== null && $coupon->starts_at->gt($now)) {
            return CouponCheck::invalid('not_started');
        }

        if ($coupon->ends_at !== null && $coupon->ends_at->lt($now)) {
            return CouponCheck::invalid('expired');
        }

        // Pentru "percent", value este în puncte de bază (1000 = 10%).
        if ($coupon->type === 'percent' && ($coupon->value < 1 || $coupon->value > 10000)) {
            return CouponCheck::invalid('invalid_value');
        }

        if ($coupon->min_subtotal !== null && $subtotal->amount() < $coupon->min_subtotal) {
            return CouponCheck::invalid('min_subtotal');
        }

        if ($coupon->max_uses !== null && $coupon->usages()->count() >= $coupon->max_uses) {
            return CouponCheck::invalid('exhausted');
        }

        if ($coupon->max_uses_per_user !== null) {
            if ($userId === null) {
                return CouponCheck::invalid('login_required');
            }

            $used = $coupon->usages()->where('user_id', $userId)->count();

            if ($used >= $coupon->max_uses_per_user) {
                return CouponCheck::invalid('user_limit');
            }
        }

        return CouponCheck::ok();
    }
}
