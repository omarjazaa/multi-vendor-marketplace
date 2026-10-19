<?php

namespace App\Services\Payments;

use App\Models\Order;

/**
 * Interface Segregation Principle (PRD §3.1):
 * Segregates refund capability so consumers or alternative gateway implementations
 * can depend specifically on refund semantics without forcing coupling to payment initiation.
 */
interface RefundablePaymentInterface
{
    /**
     * Issue a refund for a previously settled or placed order.
     *
     * @param  Order  $order  The order being refunded
     * @param  string|null  $reason  Optional reason for the refund or dispute resolution
     */
    public function refund(Order $order, ?string $reason = null): RefundResult;
}
