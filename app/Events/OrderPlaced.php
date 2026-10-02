<?php

namespace App\Events;

use App\Models\Order;
use App\Models\User;
use App\Services\Checkout\CheckoutOutcome;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Fired once per completed checkout, after the order transaction has
 * committed and the payment strategies have run.
 *
 * Carries the per-vendor split orders, the customer and the full checkout
 * outcome (per-order payment results), so listeners can build both the
 * customer confirmation and the per-vendor alerts from one event. Firing
 * after the commit is deliberate: a listener must never be able to roll
 * back a placed order, and a failed delivery is reported, not thrown.
 */
class OrderPlaced
{
    use Dispatchable, SerializesModels;

    /**
     * @param  Collection<int, Order>  $orders  one order per vendor, in first-seen store order
     */
    public function __construct(
        public readonly Collection $orders,
        public readonly User $customer,
        public readonly CheckoutOutcome $outcome,
    ) {}
}
