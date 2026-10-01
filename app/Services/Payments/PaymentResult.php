<?php

namespace App\Services\Payments;

/**
 * Immutable outcome of one payment attempt against one split order.
 *
 * Strategies never signal a decline by throwing: the orders are already
 * committed by the time a strategy runs, so a failure has to be reported as
 * data the caller can surface without touching the stored order.
 */
final class PaymentResult
{
    private function __construct(
        public readonly bool $successful,
        public readonly ?string $reference,
        public readonly ?string $message,
    ) {}

    /**
     * The provider accepted the payment (or accepted it for later
     * settlement); a reference identifies the attempt.
     */
    public static function successful(?string $reference, ?string $message = null): self
    {
        return new self(true, $reference, $message);
    }

    /** The provider refused the payment, so no reference is issued. */
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
