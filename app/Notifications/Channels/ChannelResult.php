<?php

namespace App\Notifications\Channels;

/**
 * Immutable outcome of one channel delivery attempt against one recipient.
 *
 * Channels never signal a refused or failed delivery by throwing: the orders
 * are already committed by the time a channel runs, so a failure has to be
 * reported as data the caller can log without touching the stored order.
 * Mirrors Day 17's PaymentResult value object.
 */
final class ChannelResult
{
    private function __construct(
        public readonly bool $delivered,
        public readonly string $channel,
        public readonly ?string $reference,
        public readonly ?string $message,
    ) {}

    /**
     * The channel accepted the payload for delivery (or deferred it to the
     * queue); a reference identifies the attempt for support tracing.
     */
    public static function delivered(string $channel, ?string $reference = null, ?string $message = null): self
    {
        return new self(true, $channel, $reference, $message);
    }

    /** The channel refused the payload, so no reference is issued. */
    public static function failed(string $channel, string $message): self
    {
        return new self(false, $channel, null, $message);
    }

    /** @return array{delivered: bool, channel: string, reference: string|null, message: string|null} */
    public function toArray(): array
    {
        return [
            'delivered' => $this->delivered,
            'channel' => $this->channel,
            'reference' => $this->reference,
            'message' => $this->message,
        ];
    }
}
