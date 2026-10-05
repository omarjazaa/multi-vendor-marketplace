<?php

namespace App\States;

use App\Enums\OrderStatus;
use App\Exceptions\UnsupportedOrderStateException;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the order state for a status from the config-driven registry in
 * marketplace.orders.states.
 *
 * Mirrors Day 17's PaymentStrategyFactory and Day 18's
 * NotificationChannelFactory on purpose: the map — not this class — is the
 * single source of truth for which statuses exist and what implements them,
 * so registering a state never requires editing the factory.
 */
final class OrderStateFactory
{
    public function __construct(private readonly Container $container) {}

    /**
     * Build the state registered for the given status.
     *
     * @throws UnsupportedOrderStateException when the status is not registered
     */
    public function make(OrderStatus|string $status): OrderState
    {
        $key = strtolower(trim($status instanceof OrderStatus ? $status->value : $status));
        $map = (array) config('marketplace.orders.states', []);
        $class = $map[$key] ?? null;

        if ($class === null) {
            throw UnsupportedOrderStateException::unsupported($key, $this->supported());
        }

        $resolved = $this->container->make($class);

        if (! $resolved instanceof OrderState) {
            throw UnsupportedOrderStateException::misconfigured($key, (string) $class);
        }

        return $resolved;
    }

    /** @return list<string> the registered status keys, in configuration order */
    public function supported(): array
    {
        return array_keys((array) config('marketplace.orders.states', []));
    }
}
