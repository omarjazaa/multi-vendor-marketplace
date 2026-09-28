<?php

namespace App\Services\Pricing;

use App\Enums\DiscountType;

/**
 * Applies an automatic (no code required) discount — a fixed amount or a
 * percentage — on top of the wrapped price. The reduction can never push the
 * running total below zero.
 */
class DiscountDecorator extends PriceDecorator
{
    public function __construct(
        PriceComponentInterface $inner,
        private readonly DiscountType $type,
        private readonly float $value,
    ) {
        parent::__construct($inner);
    }

    public function amount(): float
    {
        return DiscountCalculator::apply($this->inner->amount(), $this->type, $this->value);
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
