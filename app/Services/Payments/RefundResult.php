<?php

namespace App\Services\Payments;

/**
 * Immutable outcome of a refund attempt against an order through a payment gateway.
 *
 * Symmetrical with PaymentResult (Day 17): gateways never signal a decline
 * or inability to refund by unhandled exceptions alone; they report structured
 * data so domain callers can distinguish technical crashes from gateway refusals.
 */
final class RefundResult
{
    private function __construct(
        public readonly bool $successful,
        public readonly ?string $reference,
        public readonly ?string $message,
    ) {}

    /** The provider accepted and processed the refund. */
    public static function successful(?string $reference, ?string $message = null): self
    {
        return new self(true, $reference, $message);
    }

    /** The provider declined or could not process the refund. */
    public static function failed(string $message): self
    {
        return new self(false, null, $message);
    }

    /** @return array{successful: bool, reference: string|null, message: string|null} */
    public function toArray(): array
    {
        return [
            'successful' => $this->successful,
            'reference' => $this->reference,
            'message' => $this->message,
        ];
    }
}
