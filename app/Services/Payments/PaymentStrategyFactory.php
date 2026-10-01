<?php

namespace App\Services\Payments;

use App\Exceptions\UnsupportedPaymentMethodException;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the payment strategy for a method from the config-driven map in
 * marketplace.payments.methods.
 *
 * The map — not this class — is the single source of truth for which methods
 * exist and what handles them, so registering a strategy never requires
 * editing the factory.
 */
final class PaymentStrategyFactory
{
    public function __construct(private readonly Container $container) {}

    /**
     * Build the strategy registered for the given method.
     *
     * @throws UnsupportedPaymentMethodException when the method is not registered
     */
    public function make(string $method): PaymentStrategyInterface
    {
        $key = strtolower(trim($method));
        $map = (array) config('marketplace.payments.methods', []);
        $class = $map[$key] ?? null;

        if ($class === null) {
            throw UnsupportedPaymentMethodException::unsupported($key, $this->supported());
        }

        $strategy = $this->container->make($class);

        if (! $strategy instanceof PaymentStrategyInterface) {
            throw UnsupportedPaymentMethodException::misconfigured($key, (string) $class);
        }

        return $strategy;
    }

    /** @return list<string> the registered method keys, in configuration order */
    public function supported(): array
    {
        return array_keys((array) config('marketplace.payments.methods', []));
    }
}
