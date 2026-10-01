<?php

namespace App\Services\Payments\Strategies;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderTransitionService;
use App\Services\Payments\PaymentResult;
use App\Services\Payments\PaymentStrategyInterface;

/**
 * Simulated card gateway: the outcome comes from
 * marketplace.payments.card.simulate_success instead of a real provider.
 *
 * An approved charge settles the order through the shared lifecycle map
 * (pending -> paid) rather than writing the status directly, so the order
 * obeys exactly the same transition rules as an admin action. A declined
 * charge reports itself and leaves the order untouched and unpaid.
 */
final class CardPaymentStrategy implements PaymentStrategyInterface
{
    private const PREFIX = 'CARD';

    public function __construct(private readonly OrderTransitionService $transitions) {}

    public function method(): string
    {
        return 'card';
    }

    public function pay(Order $order): PaymentResult
    {
        if (! (bool) config('marketplace.payments.card.simulate_success', false)) {
            return PaymentResult::failed((string) config(
                'marketplace.payments.card.decline_message',
                'Your card was declined. Try another payment method.',
            ));
        }

        $this->transitions->apply($order, OrderStatus::PAID);

        return PaymentResult::successful(
            sprintf('%s-%06d', self::PREFIX, $order->id),
            'Card payment approved.',
        );
    }
}
