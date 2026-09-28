<?php

namespace App\Services\Pricing;

use App\Enums\DiscountType;

/** Stateless discount arithmetic shared by automatic discounts and coupons. */
final class DiscountCalculator
{
    /**
     * Reduce $amount by a fixed value or a percentage, never below zero
     * and always rounded to 2dp (percentages above 100 simply zero out).
     */
    public static function apply(float $amount, DiscountType $type, float $value): float
    {
        $discount = $type === DiscountType::PERCENTAGE
            ? round($amount * max(0, $value) / 100, 2)
            : max(0, $value);

        return round(max(0, $amount - min($amount, $discount)), 2);
    }
}
