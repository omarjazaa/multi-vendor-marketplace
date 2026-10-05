<?php

namespace App\States;

use App\Enums\OrderStatus;

final class PendingState implements OrderState
{
    public function status(): OrderStatus
    {
        return OrderStatus::PENDING;
    }

    /** @return list<string> the configured edges for 'pending' */
    public function allowedTransitions(): array
    {
        return (array) config('marketplace.orders.transitions.pending', []);
    }
}
