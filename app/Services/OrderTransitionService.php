<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;

/**
 * Applies the config-driven order lifecycle map (marketplace.orders.transitions).
 *
 * The rules live here so every actor shares one gate: the admin/vendor
 * controllers through OrderService::transitionTo(), and the payment
 * strategies that settle an order during checkout — which keeps the card
 * strategy from having to depend on OrderService itself.
 */
class OrderTransitionService
{
    public function __construct(private readonly OrderRepositoryInterface $orders) {}

    /**
     * Move an order to the target status when the configured map allows it.
     *
     * @throws InvalidOrderTransitionException when the transition is not allowed
     */
    public function apply(Order $order, OrderStatus $target): Order
    {
        $allowed = (array) config("marketplace.orders.transitions.{$order->status->value}", []);

        if (! in_array($target->value, $allowed, true)) {
            throw new InvalidOrderTransitionException($order->status->value, $target->value);
        }

        return $this->orders->updateStatus($order, $target);
    }
}
