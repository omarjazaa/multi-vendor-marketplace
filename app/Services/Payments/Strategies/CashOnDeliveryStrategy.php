<?php

namespace App\Services\Payments\Strategies;

use App\Models\Order;
use App\Services\Payments\PaymentResult;
use App\Services\Payments\PaymentStrategyInterface;

/**
 * Cash on delivery: the order is accepted and its status is left alone, so
 * the customer still owes the money until a vendor marks the order paid.
 */
final class CashOnDeliveryStrategy implements PaymentStrategyInterface
{
    private const PREFIX = 'COD';

    public function method(): string
    {
        return 'cod';
    }

    public function pay(Order $order): PaymentResult
    {
        return PaymentResult::successful(
            sprintf('%s-%06d', self::PREFIX, $order->id),
            'Cash on delivery recorded. Pay the courier when your order arrives.',
        );
    }
}
