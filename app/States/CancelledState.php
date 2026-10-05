<?php

namespace App\States;

use App\Enums\OrderStatus;

final class CancelledState implements OrderState
{
    public function status(): OrderStatus
    {
        return OrderStatus::CANCELLED;
    }

    /** @return list<string> a cancellation is terminal: [] */
    public function allowedTransitions(): array
    {
        return (array) config('marketplace.orders.transitions.cancelled', []);
    }
}
