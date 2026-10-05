<?php

namespace App\States;

use App\Enums\OrderStatus;

final class RefundedState implements OrderState
{
    public function status(): OrderStatus
    {
        return OrderStatus::REFUNDED;
    }

    /** @return list<string> a refund is terminal: [] */
    public function allowedTransitions(): array
    {
        return (array) config('marketplace.orders.transitions.refunded', []);
    }
}
