<?php

namespace App\Services\Payments;

use App\Models\Order;

/**
 * One payment method's behaviour, resolved at runtime by
 * PaymentStrategyFactory from marketplace.payments.methods. Checkout never
 * branches on the method itself, so a new method is a new class plus a
 * config entry.
 */
interface PaymentStrategyInterface
{
    /** The method this strategy handles; matches its marketplace.payments.methods key. */
    public function method(): string;

    /**
     * Attempt to pay a single split order.
     *
     * Implementations report a decline through the returned result instead of
     * throwing: the order is committed before a strategy runs, so a failure
     * must leave it intact and unpaid.
     */
    public function pay(Order $order): PaymentResult;
}
