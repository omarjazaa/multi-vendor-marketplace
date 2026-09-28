<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a coupon cannot take part in a price calculation: the code does
 * not exist, or the coupon's own rules (expiry, usage limit, minimum order)
 * reject it. Carries a customer-facing message the API can return as a 422.
 */
class InvalidCouponException extends RuntimeException
{
    public static function notFound(string $code): self
    {
        return new self("No coupon found for code {$code}.");
    }

    public static function ineligible(string $reason): self
    {
        return new self($reason);
    }
}
