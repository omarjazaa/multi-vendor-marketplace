<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a status has no registered state in
 * marketplace.orders.states (or maps to a class that is not a state).
 * Mirrors Day 17's UnsupportedPaymentMethodException and Day 18's
 * UnsupportedNotificationChannelException so all three factories read
 * the same way.
 *
 * This fires on a config defect — the API cannot reach an unregistered
 * key because the FormRequests validate unknown status strings with 422
 * before any state is resolved, so no route/controller mapping is added.
 */
class UnsupportedOrderStateException extends RuntimeException
{
    /** @param  list<string>  $supported  */
    public function __construct(
        public readonly string $status,
        string $message,
        public readonly array $supported = [],
    ) {
        parent::__construct($message);
    }

    /** No state is registered for the requested status. */
    public static function unsupported(string $status, array $supported = []): self
    {
        $message = sprintf('Order status "%s" is not supported.', $status);

        if ($supported !== []) {
            $message .= sprintf(' Supported statuses: %s.', implode(', ', $supported));
        }

        return new self($status, $message, $supported);
    }

    /** The configured class does not implement OrderState. */
    public static function misconfigured(string $status, string $class): self
    {
        return new self(
            $status,
            sprintf('Order status "%s" is misconfigured: %s is not an order state.', $status, $class),
        );
    }
}
