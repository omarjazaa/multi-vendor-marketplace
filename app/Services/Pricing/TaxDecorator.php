<?php

namespace App\Services\Pricing;

/**
 * Applies the configured tax rate to the running (already discounted) price.
 * The tax is rounded to 2dp on its own, keeping every money figure in the
 * breakdown independently verifiable.
 */
class TaxDecorator extends PriceDecorator
{
    public function __construct(
        PriceComponentInterface $inner,
        private readonly float $rate,
    ) {
        parent::__construct($inner);
    }

    public function amount(): float
    {
        return round($this->inner->amount() + $this->taxAmount(), 2);
    }

    public function breakdown(): array
    {
        $breakdown = $this->inner->breakdown();
        $breakdown['tax'] = round($breakdown['tax'] + $this->taxAmount(), 2);

        return $breakdown;
    }

    /** Tax owed on the wrapped price; a negative rate is treated as zero. */
    private function taxAmount(): float
    {
        return round($this->inner->amount() * max(0, $this->rate), 2);
    }
}
