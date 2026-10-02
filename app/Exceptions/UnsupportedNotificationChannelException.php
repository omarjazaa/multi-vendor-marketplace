<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a notification channel has no registered implementation in
 * marketplace.notifications.channels (or maps to a class that is not a
 * channel). Mirrors Day 17's UnsupportedPaymentMethodException so the
 * channel factory reads the same way as the payment factory.
 */
class UnsupportedNotificationChannelException extends RuntimeException
{
    /** @param  list<string>  $supported  */
    public function __construct(
        public readonly string $channel,
        string $message,
        public readonly array $supported = [],
    ) {
        parent::__construct($message);
    }

    /** No channel is registered for the requested key. */
    public static function unsupported(string $channel, array $supported = []): self
    {
        $message = sprintf('Notification channel "%s" is not supported.', $channel);

        if ($supported !== []) {
            $message .= sprintf(' Supported channels: %s.', implode(', ', $supported));
        }

        return new self($channel, $message, $supported);
    }

    /** The configured class does not implement NotificationChannelInterface. */
    public static function misconfigured(string $channel, string $class): self
    {
        return new self(
            $channel,
            sprintf('Notification channel "%s" is misconfigured: %s is not a notification channel.', $channel, $class),
        );
    }
}
