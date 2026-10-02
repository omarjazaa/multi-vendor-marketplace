<?php

namespace App\Notifications\Channels;

/**
 * Contract every order-notification delivery channel honours.
 *
 * Mirrors Day 17's PaymentStrategyInterface: each channel names the key it
 * answers to (matching a marketplace.notifications.channels entry) and
 * reports its outcome as data rather than throwing for a failed delivery.
 */
interface NotificationChannelInterface
{
    /** The channel key this implementation answers to. */
    public function channel(): string;

    /**
     * Deliver the payload to the notifiable user.
     *
     * The returned result — not an exception — carries success or failure.
     */
    public function send(object $notifiable, NotificationPayload $payload): ChannelResult;
}
