<?php

namespace App\Notifications\Channels;

/**
 * Immutable content for one notification delivery attempt.
 *
 * Built by the notification service per recipient, then handed to a channel,
 * mirroring how Day 17 hands a PaymentResult-shaped outcome back to its
 * caller. The kind field lets a channel format customer confirmations and
 * vendor alerts differently without branching on order internals.
 */
final class NotificationPayload
{
    /**
     * @param  string  $kind  'customer_confirmation' or 'vendor_new_order'
     * @param  array<string, mixed>  $data  pre-formatted content for the channels
     */
    private function __construct(
        public readonly string $kind,
        public readonly string $subject,
        public readonly string $headline,
        public readonly array $data,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function make(string $kind, string $subject, string $headline, array $data = []): self
    {
        return new self($kind, $subject, $headline, $data);
    }

    /** @return array{kind: string, subject: string, headline: string, data: array<string, mixed>} */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'subject' => $this->subject,
            'headline' => $this->headline,
            'data' => $this->data,
        ];
    }
}
