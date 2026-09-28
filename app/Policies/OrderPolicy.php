<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;

class OrderPolicy
{
    /** Determine whether the user may view the order: the customer or an admin. */
    public function view(User $user, Order $order): bool
    {
        return $order->user_id === $user->id || $user->hasRole('admin');
    }

    /**
     * Determine whether the user may transition the order's status.
     *
     * Admins may move any order; vendors only orders that contain at least one
     * line from their own stores; customers who placed the order may not —
     * fulfilment is staff work, tracked through the configured transition map.
     */
    public function update(User $user, Order $order): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if (! $user->hasRole('vendor')) {
            return false;
        }

        return OrderItem::where('order_id', $order->id)
            ->whereHas('product.store.vendorProfile', fn ($query) => $query->where('user_id', $user->id))
            ->exists();
    }
}
