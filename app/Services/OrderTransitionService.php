<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Events\OrderPaid;
use App\Events\OrderShipped;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\States\OrderStateFactory;

/**
 * Applies the order lifecycle State pattern: the OrderState resolved for the
 * order's current status answers which transitions are legal.
 *
 * marketplace.orders.transitions (the lifecycle matrix) feeds every state's
 * allowedTransitions(), and a sync unit test keeps the states registry, the
 * matrix and the OrderStatus enum aligned — so this gate consults objects,
 * never config directly.
 *
 * The rules live here so every actor shares one gate: the admin/vendor
 * controllers through OrderService::transitionTo(), and the payment
 * strategies that settle an order during checkout — which keeps the card
 * strategy from having to depend on OrderService itself. Authorization
 * (who may apply a transition) stays in the controller/policy layer.
 *
 * Being the one gate also makes it the Day 20 emission point: a legal move
 * into paid or shipped announces itself with OrderPaid / OrderShipped
 * exactly once, whoever drove the transition — observers never care which
 * actor did it, and a blocked transition throws before reaching the
 * dispatch.
 */
class OrderTransitionService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly OrderStateFactory $states,
    ) {}

    /**
     * Move an order to the target status when its current state allows it,
     * then announce the Day 20 lifecycle event for moves the observers care
     * about (paid, shipped) — after the write, so listeners always read the
     * new status from the payload.
     *
     * @throws InvalidOrderTransitionException when the transition is not allowed
     */
    public function apply(Order $order, OrderStatus $target): Order
    {
        $state = $this->states->make($order->status);

        if (! in_array($target->value, $state->allowedTransitions(), true)) {
            throw new InvalidOrderTransitionException($order->status->value, $target->value);
        }

        $updated = $this->orders->updateStatus($order, $target);

        match ($target) {
            OrderStatus::PAID => OrderPaid::dispatch($updated),
            OrderStatus::SHIPPED => OrderShipped::dispatch($updated),
            default => null,
        };

        return $updated;
    }
}
