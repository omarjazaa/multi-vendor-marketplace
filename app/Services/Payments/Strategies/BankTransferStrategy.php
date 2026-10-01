<?php

namespace App\Services\Payments\Strategies;

use App\Models\Order;
use App\Services\Payments\PaymentResult;
use App\Services\Payments\PaymentStrategyInterface;

/**
 * Bank transfer: the instructions are issued and the order stays in its
 * initial status until an admin confirms the incoming transfer.
 */
final class BankTransferStrategy implements PaymentStrategyInterface
{
    private const PREFIX = 'BANK';

    public function method(): string
    {
        return 'bank_transfer';
    }

    public function pay(Order $order): PaymentResult
    {
        return PaymentResult::successful(
            sprintf('%s-%06d', self::PREFIX, $order->id),
            'Bank transfer details issued. The order stays unpaid until the transfer is confirmed.',
        );
    }
}
