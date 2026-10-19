<?php

namespace App\Services\Payments\Strategies;

use App\Models\Order;
use App\Services\Payments\PaymentGatewayInterface;
use App\Services\Payments\PaymentResult;
use App\Services\Payments\RefundResult;

/**
 * Bank transfer: the instructions are issued and the order stays in its
 * initial status until an admin confirms the incoming transfer.
 *
 * Day 27: Bank transfer refund initiates reversal to customer account.
 */
final class BankTransferStrategy implements PaymentGatewayInterface
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

    public function refund(Order $order, ?string $reason = null): RefundResult
    {
        return RefundResult::successful(
            sprintf('REF-%s-%06d', self::PREFIX, $order->id),
            'Bank transfer refund initiated. Reversal submitted to customer bank account.',
        );
    }
}
