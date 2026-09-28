<?php

namespace App\Enums;

/**
 * Shared money-reduction shape for both coupons and automatic discounts:
 * a fixed amount off, or a percentage of the running price.
 */
enum DiscountType: string
{
    case FIXED = 'fixed';
    case PERCENTAGE = 'percentage';
}
