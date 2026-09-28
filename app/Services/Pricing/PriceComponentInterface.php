<?php

namespace App\Services\Pricing;

/**
 * Decorator-pattern pricing pipeline (PRD §3.2): every component wraps the
 * previous one and contributes its part of the final price transparently —
 * base subtotal → automatic discount → coupon → tax. Components only see the
 * running price, so new components can be added without touching existing ones.
 */
interface PriceComponentInterface
{
    /** Running total after this component has been applied, rounded to 2dp. */
    public function amount(): float;

    /**
     * Discount and tax accumulated by every component in the chain.
     *
     * @return array{discount: float, tax: float}
     */
    public function breakdown(): array;
}
