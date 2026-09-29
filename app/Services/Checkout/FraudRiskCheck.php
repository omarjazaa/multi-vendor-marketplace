<?php

namespace App\Services\Checkout;

use App\Exceptions\FraudRiskException;
use App\Repositories\Contracts\OrderRepositoryInterface;

/**
 * Third link of the checkout chain: reject carts that trip the config-driven
 * risk heuristics under marketplace.checkout.fraud. The rules are declared
 * in configuration so ops can tune the ceilings per environment without a
 * code change; a zero disables its rule.
 */
class FraudRiskCheck extends CheckoutValidationHandler
{
    public function __construct(private readonly OrderRepositoryInterface $orders) {}

    /** @throws FraudRiskException when a risk rule rejects the checkout */
    protected function validate(CheckoutContext $context): void
    {
        $ceiling = (float) config('marketplace.checkout.fraud.max_order_total', 0);

        if ($ceiling > 0 && $context->subtotal > $ceiling) {
            throw FraudRiskException::orderTotalExceeded($context->subtotal, $ceiling);
        }

        $maxOrders = (int) config('marketplace.checkout.fraud.max_orders_per_window', 0);

        if ($maxOrders <= 0) {
            return;
        }

        $windowMinutes = (int) config('marketplace.checkout.fraud.window_minutes', 60);
        $recent = $this->orders->countForUserSince(
            $context->userId(),
            now()->subMinutes($windowMinutes),
        );

        if ($recent >= $maxOrders) {
            throw FraudRiskException::tooManyRecentOrders($recent, $maxOrders, $windowMinutes);
        }
    }
}
