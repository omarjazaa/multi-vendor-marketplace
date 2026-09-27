<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /** Determine whether the user may view the order: the customer or an admin. */
    public function view(User $user, Order $order): bool
    {
        return $order->user_id === $user->id || $user->hasRole('admin');
    }
}
