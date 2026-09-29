<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised by the checkout validation chain when a cart trips one of the
 * config-driven fraud heuristics (marketplace.checkout.fraud). Carries the
 * machine-readable rule name alongside a customer-facing message so the
 * API can name the rule it enforced in its 422 response.
 */
class FraudRiskException extends RuntimeException
{
    public function __construct(
        public readonly string $rule,
        string $message,
    ) {
        parent::__construct($message);
    }

    /** The cart subtotal is above the configured per-order ceiling. */
    public static function orderTotalExceeded(float $subtotal, float $ceiling): self
    {
        return new self(
            'max_order_total',
            sprintf('Order total of %.2f exceeds the allowed limit of %.2f.', $subtotal, $ceiling),
        );
    }

    /** The customer placed too many orders inside the risk window. */
    public static function tooManyRecentOrders(int $recent, int $maxOrders, int $windowMinutes): self
    {
        return new self(
            'max_orders_per_window',
            sprintf(
                'Too many orders in a short window: %d placed within the last %d minutes (maximum %d).',
                $recent,
                $windowMinutes,
                $maxOrders,
            ),
        );
    }
}
