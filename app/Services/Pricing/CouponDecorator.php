<?php

namespace App\Services\Pricing;

use App\Exceptions\InvalidCouponException;
use App\Models\Coupon;

/**
 * Wraps the running price with a coupon's discount while enforcing the
 * coupon's eligibility rules (expiry, usage limit, minimum order) up front,
 * so an inapplicable coupon fails fast instead of silently mispricing.
 *
 * @throws InvalidCouponException when the coupon cannot be applied
 */
class CouponDecorator extends PriceDecorator
{
    public function __construct(PriceComponentInterface $inner, private readonly Coupon $coupon)
    {
        parent::__construct($inner);

        $reason = $coupon->rejectionReasonFor($inner->amount());
        if ($reason !== null) {
            throw InvalidCouponException::ineligible($reason);
        }
    }

    public function amount(): float
    {
        return DiscountCalculator::apply(
            $this->inner->amount(),
            $this->coupon->type,
            (float) $this->coupon->value,
        );
    }

    public function breakdown(): array
    {
        $breakdown = $this->inner->breakdown();
        $breakdown['discount'] = round(
            $breakdown['discount'] + ($this->inner->amount() - $this->amount()),
            2,
        );

        return $breakdown;
    }
}
