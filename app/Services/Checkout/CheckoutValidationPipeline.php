<?php

namespace App\Services\Checkout;

use App\Exceptions\FraudRiskException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidCouponException;

/**
 * Wires the checkout validation chain in its fixed order — stock, coupon,
 * fraud — and runs it against a context. The composition lives here so the
 * order of the guards is declared exactly once and the order service only
 * has to ask for a verdict.
 */
class CheckoutValidationPipeline
{
    public function __construct(
        private readonly StockAvailabilityCheck $stock,
        private readonly CouponValidityCheck $coupon,
        private readonly FraudRiskCheck $fraud,
    ) {}

    /**
     * Run every guard against the context; the first rejection throws and
     * halts the chain before any database write happens.
     *
     * @throws InsufficientStockException when stock fell short
     * @throws InvalidCouponException when the coupon is rejected
     * @throws FraudRiskException when a fraud rule trips
     */
    public function run(CheckoutContext $context): void
    {
        $this->stock->setNext($this->coupon)->setNext($this->fraud);

        $this->stock->handle($context);
    }
}
