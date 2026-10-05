<?php

namespace App\States;

use App\Enums\OrderStatus;

final class ShippedState implements OrderState
{
    public function status(): OrderStatus
    {
        return OrderStatus::SHIPPED;
    }

    /** @return list<string> the configured edges for 'shipped' */
    public function allowedTransitions(): array
    {
        return (array) config('marketplace.orders.transitions.shipped', []);
    }
}
