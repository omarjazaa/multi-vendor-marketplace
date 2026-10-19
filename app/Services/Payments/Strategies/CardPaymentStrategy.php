<?php

namespace App\Services\Payments\Strategies;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderTransitionService;
use App\Services\Payments\PaymentGatewayInterface;
use App\Services\Payments\PaymentResult;
use App\Services\Payments\RefundResult;

/**
 * Simulated card gateway: the outcome comes from
 * marketplace.payments.card.simulate_success instead of a real provider.
 *
 * An approved charge settles the order through the shared lifecycle map
 * (pending -> paid) rather than writing the status directly, so the order
 * obeys exactly the same transition rules as an admin action. A declined
 * charge reports itself and leaves the order untouched and unpaid.
 *
 * Day 27: Polymorphic gateway refund simulates processor reversal, guarded
 * by marketplace.payments.card.simulate_refund_failure.
 */
final class CardPaymentStrategy implements PaymentGatewayInterface
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

    public function refund(Order $order, ?string $reason = null): RefundResult
    {
        if ((bool) config('marketplace.payments.card.simulate_refund_failure', false)) {
            return RefundResult::failed(
                (string) config(
                    'marketplace.payments.card.refund_decline_message',
                    'Card refund declined by payment processor.',
                ),
            );
        }

        return RefundResult::successful(
            sprintf('REF-%s-%06d', self::PREFIX, $order->id),
            'Card payment refunded successfully.',
        );
    }
}
