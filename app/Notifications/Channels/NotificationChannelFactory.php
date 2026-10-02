<?php

namespace App\Notifications\Channels;

use App\Exceptions\UnsupportedNotificationChannelException;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the notification channel for a key from the config-driven map in
 * marketplace.notifications.channels.
 *
 * Mirrors Day 17's PaymentStrategyFactory on purpose: the map — not this
 * class — is the single source of truth for which channels exist and what
 * implements them, so registering a channel never requires editing the
 * factory.
 */
final class NotificationChannelFactory
{
    public function __construct(private readonly Container $container) {}

    /**
     * Build the channel registered for the given key.
     *
     * @throws UnsupportedNotificationChannelException when the key is not registered
     */
    public function make(string $channel): NotificationChannelInterface
    {
        $key = strtolower(trim($channel));
        $map = (array) config('marketplace.notifications.channels', []);
        $class = $map[$key] ?? null;

        if ($class === null) {
            throw UnsupportedNotificationChannelException::unsupported($key, $this->supported());
        }

        $resolved = $this->container->make($class);

        if (! $resolved instanceof NotificationChannelInterface) {
            throw UnsupportedNotificationChannelException::misconfigured($key, (string) $class);
        }

        return $resolved;
    }

    /** @return list<string> the registered channel keys, in configuration order */
    public function supported(): array
    {
        return array_keys((array) config('marketplace.notifications.channels', []));
    }
}
