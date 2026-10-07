<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired by the shared lifecycle gate (OrderTransitionService::apply) when
 * an order transitions into the shipped state, whoever drove it: the
 * vendor shipping their line or an admin acting on fulfilment.
 *
 * The Day 21 mock-shipment module will generate the tracking number and
 * move the status through this same gate, so it must NOT dispatch this
 * event directly — extending this payload with the tracking data here is
 * the single point of change when that day lands.
 */
class OrderShipped
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Order $order) {}
}
