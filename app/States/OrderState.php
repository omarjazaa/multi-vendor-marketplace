<?php

namespace App\States;

use App\Enums\OrderStatus;

/**
 * State Pattern: the object the transition gate consults for an order's
 * current status.
 *
 * States are stateless — they answer questions about a status, they never
 * mutate the order (persistence stays in OrderTransitionService via the
 * repository). Their allowed edges come from the lifecycle matrix in
 * config/marketplace.php (marketplace.orders.transitions), which remains the
 * single source of truth; a sync unit test keeps every state's answer
 * aligned with its configured row.
 */
interface OrderState
{
    public function status(): OrderStatus;

    /**
     * The status values this state may move to, as configured.
     *
     * @return list<string>
     */
    public function allowedTransitions(): array;
}
