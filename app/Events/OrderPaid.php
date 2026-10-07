<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired by the shared lifecycle gate (OrderTransitionService::apply) the
 * moment an order lands in the paid state — no matter which actor drove
 * it: an approved card charge during checkout, or an admin/vendor action.
 *
 * Carries the freshly updated order so listeners read the paid status and
 * payment method straight from the payload. The payment reference is
 * recorded by checkout immediately after the charge settles, so it is not
 * part of this payload — listeners that need it read the order later.
 *
 * Emitted after the status write succeeded: a blocked transition throws
 * before it ever reaches the dispatch, so observers only ever see legal,
 * committed state changes.
 */
class OrderPaid
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Order $order) {}
}
