<?php

namespace App\Services\Checkout;

/**
 * Base link of the checkout validation chain (Chain of Responsibility).
 *
 * Handlers are linked in a fixed order — stock, coupon, fraud — and each
 * one either passes the context to the next link or halts the chain by
 * throwing a typed exception that the controller maps to an HTTP error.
 */
abstract class CheckoutValidationHandler
{
    private ?CheckoutValidationHandler $next = null;

    /**
     * Link the handler that runs when this one passes.
     *
     * @return CheckoutValidationHandler the linked handler, so chains can be
     *                                   wired fluently: $first->setNext($second)->setNext($third)
     */
    public function setNext(CheckoutValidationHandler $handler): CheckoutValidationHandler
    {
        $this->next = $handler;

        return $handler;
    }

    /** Run this handler and, when it does not throw, the rest of the chain. */
    final public function handle(CheckoutContext $context): void
    {
        $this->validate($context);

        $this->next?->handle($context);
    }

    /**
     * Apply this handler's rule, throwing when the checkout must stop.
     *
     * @throws \RuntimeException a typed subclass naming the rejection
     */
    abstract protected function validate(CheckoutContext $context): void;
}
