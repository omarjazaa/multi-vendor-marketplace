<?php

namespace App\Services\Checkout;

use App\Exceptions\InvalidCouponException;
use App\Repositories\Contracts\CouponRepositoryInterface;

/**
 * Second link of the checkout chain: resolve the optional coupon code and
 * enforce its eligibility rules.
 *
 * The rules themselves live on the Coupon model (rejectionReasonFor), so
 * the cart preview endpoint and the checkout chain reject with exactly the
 * same message; an accepted coupon is parked on the context for the
 * pricing step inside the checkout transaction.
 */
class CouponValidityCheck extends CheckoutValidationHandler
{
    public function __construct(private readonly CouponRepositoryInterface $coupons) {}

    /** @throws InvalidCouponException when the code is unknown or ineligible */
    protected function validate(CheckoutContext $context): void
    {
        // Codes are stored uppercase; normalising here keeps programmatic
        // service calls (not just HTTP requests) case-insensitive too.
        $code = strtoupper(trim((string) $context->couponCode));

        if ($code === '') {
            return;
        }

        $coupon = $this->coupons->findByCode($code);

        if ($coupon === null) {
            throw InvalidCouponException::notFound($code);
        }

        $reason = $coupon->rejectionReasonFor($context->subtotal);

        if ($reason !== null) {
            throw InvalidCouponException::ineligible($reason);
        }

        $context->coupon = $coupon;
    }
}
