<?php

namespace App\Events;

use App\Models\Dispute;
use App\Models\Order;
use App\Services\Payments\RefundResult;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an order transitions into the refunded state (Day 27).
 *
 * Dispatched following a successful polymorphic gateway refund, whether
 * triggered via admin dispute resolution or direct admin refund action.
 */
class OrderRefunded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly ?RefundResult $refundResult = null,
        public readonly ?Dispute $dispute = null,
    ) {}
}
