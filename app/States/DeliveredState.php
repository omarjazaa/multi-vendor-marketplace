<?php

namespace App\States;

use App\Enums\OrderStatus;

final class DeliveredState implements OrderState
{
    public function status(): OrderStatus
    {
        return OrderStatus::DELIVERED;
    }

    /** @return list<string> the configured edges for 'delivered' */
    public function allowedTransitions(): array
    {
        return (array) config('marketplace.orders.transitions.delivered', []);
    }
}
