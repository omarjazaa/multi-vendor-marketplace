<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a payment method has no registered strategy in
 * marketplace.payments.methods (or maps to a class that is not a strategy).
 * Carries the offending method so the API can answer 422 with the field the
 * customer actually sent.
 */
class UnsupportedPaymentMethodException extends RuntimeException
{
    /** @param  list<string>  $supported  */
    public function __construct(
        public readonly string $method,
        string $message,
        public readonly array $supported = [],
    ) {
        parent::__construct($message);
    }

    /** No strategy is registered for the requested method. */
    public static function unsupported(string $method, array $supported = []): self
    {
        $message = sprintf('Payment method "%s" is not supported.', $method);

        if ($supported !== []) {
            $message .= sprintf(' Supported methods: %s.', implode(', ', $supported));
        }

        return new self($method, $message, $supported);
    }

    /** The configured class does not implement PaymentStrategyInterface. */
    public static function misconfigured(string $method, string $class): self
    {
        return new self(
            $method,
            sprintf('Payment method "%s" is misconfigured: %s is not a payment strategy.', $method, $class),
        );
    }
}
