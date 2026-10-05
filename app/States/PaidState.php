<?php

namespace App\States;

use App\Enums\OrderStatus;

final class PaidState implements OrderState
{
    public function status(): OrderStatus
    {
        return OrderStatus::PAID;
    }

    /** @return list<string> the configured edges for 'paid' */
    public function allowedTransitions(): array
    {
        return (array) config('marketplace.orders.transitions.paid', []);
    }
}
