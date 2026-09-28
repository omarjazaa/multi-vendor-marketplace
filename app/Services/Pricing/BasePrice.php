<?php

namespace App\Services\Pricing;

/** The undecorated starting price: the raw cart subtotal. */
class BasePrice implements PriceComponentInterface
{
    public function __construct(private readonly float $amount) {}

    public function amount(): float
    {
        // A cart total never goes negative, no matter what garbage feeds it.
        return round(max(0, $this->amount), 2);
    }

    public function breakdown(): array
    {
        return ['discount' => 0.0, 'tax' => 0.0];
    }
}
