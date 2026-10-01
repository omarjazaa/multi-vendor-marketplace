<?php

namespace App\Services\Checkout;

use App\Models\Order;
use App\Services\Payments\PaymentResult;
use Illuminate\Support\Collection;

/**
 * What a completed checkout produced: the per-vendor orders and the payment
 * attempt made for each of them.
 *
 * Returning both together makes the post-commit payment step impossible to
 * skip silently — the caller cannot read the orders without also seeing what
 * the payment strategies reported about them.
 */
final class CheckoutOutcome
{
    /**
     * @param  Collection<int, Order>  $orders  one order per vendor, in first-seen store order
     * @param  string|null  $paymentMethod  the method used, if one was supplied
     * @param  Collection<int, PaymentResult>  $payments  keyed by order id; empty when no method was supplied
     */
    public function __construct(
        public readonly Collection $orders,
        public readonly ?string $paymentMethod,
        public readonly Collection $payments,
    ) {}

    /**
     * Whether every attempted payment succeeded. A checkout with no payment
     * method attempted nothing and counts as nothing outstanding.
     */
    public function isPaid(): bool
    {
        return $this->payments->every(fn (PaymentResult $result): bool => $result->successful);
    }

    /**
     * Payment summary for the checkout response.
     *
     * @return array{method: string|null, successful: bool, results: list<array<string, mixed>>}
     */
    public function paymentPayload(): array
    {
        return [
            'method' => $this->paymentMethod,
            'successful' => $this->isPaid(),
            'results' => $this->payments
                ->map(fn (PaymentResult $result, int $orderId): array => [
                    'order_id' => $orderId,
                    ...$result->toArray(),
                ])
                ->values()
                ->all(),
        ];
    }
}
