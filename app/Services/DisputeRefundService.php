<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransitionException;
use App\Exceptions\OrderDisputeException;
use App\Exceptions\RefundProcessingException;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\User;
use App\Repositories\Contracts\DisputeRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\Payments\PaymentStrategyFactory;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Admin dispute & refund orchestration service (Day 27).
 *
 * Implements polymorphic gateway refunds through the original strategy/gateway
 * used at checkout (Card, CashOnDelivery, BankTransfer), allowing administrators
 * to resolve customer disputes or force a RefundedState transition on orders.
 */
class DisputeRefundService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly DisputeRepositoryInterface $disputes,
        private readonly PaymentStrategyFactory $payments,
        private readonly OrderTransitionService $transitions,
    ) {}

    /**
     * File a new dispute on an order by a customer.
     *
     * @throws OrderDisputeException when the customer is not the owner or order is not dispute-eligible
     */
    public function createDispute(Order $order, User $user, string $reason): Dispute
    {
        if ($order->user_id !== $user->id) {
            throw new OrderDisputeException('You may only dispute your own orders.', 403);
        }

        if (in_array($order->status, [OrderStatus::PENDING, OrderStatus::CANCELLED], true)) {
            throw new OrderDisputeException('Unpaid or cancelled orders cannot be disputed.', 409);
        }

        if ($order->status === OrderStatus::REFUNDED) {
            throw new OrderDisputeException('An already refunded order cannot be disputed.', 409);
        }

        if ($this->disputes->hasActiveDispute($order->id)) {
            throw new OrderDisputeException('An active dispute already exists for this order.', 409);
        }

        return $this->disputes->create($order, $user, $reason);
    }

    /**
     * Process a polymorphic refund on an order.
     *
     * Invokes the registered payment gateway strategy for the order's payment_method.
     * If the gateway accepts the refund, moves the order to RefundedState and persists
     * the issued refund reference and reason.
     *
     * @throws InvalidOrderTransitionException when transition to refunded is invalid
     * @throws RefundProcessingException when the gateway declines the refund
     */
    public function refundOrder(Order $order, ?string $reason = null, bool $force = false, ?User $admin = null): Order
    {
        if ($order->status === OrderStatus::REFUNDED) {
            throw new InvalidOrderTransitionException($order->status->value, OrderStatus::REFUNDED->value);
        }

        if ($order->status === OrderStatus::PENDING) {
            throw new InvalidOrderTransitionException($order->status->value, OrderStatus::REFUNDED->value);
        }

        if ($order->status === OrderStatus::CANCELLED) {
            throw new InvalidOrderTransitionException($order->status->value, OrderStatus::REFUNDED->value);
        }

        if ($order->status === OrderStatus::SHIPPED && ! $force) {
            throw new InvalidOrderTransitionException($order->status->value, OrderStatus::REFUNDED->value);
        }

        $strategy = $this->payments->make($order->payment_method ?? '');
        $refundResult = $strategy->refund($order, $reason);

        if (! $refundResult->successful) {
            throw new RefundProcessingException(
                $refundResult->message ?? 'Payment gateway declined refund request.',
                $refundResult->message,
            );
        }

        $transitioned = $force
            ? $this->transitions->forceApply($order, OrderStatus::REFUNDED)
            : $this->transitions->apply($order, OrderStatus::REFUNDED);

        $reference = $refundResult->reference ?? sprintf('REF-%s-%06d', strtoupper($order->payment_method ?? 'GEN'), $order->id);
        $this->orders->recordRefund($transitioned, $reference, $reason);

        // Auto-resolve any open disputes on this order
        Dispute::where('order_id', $order->id)
            ->whereIn('status', ['pending', 'under_review'])
            ->get()
            ->each(function (Dispute $dispute) use ($admin, $reason): void {
                $this->disputes->updateResolution(
                    $dispute,
                    'resolved_refunded',
                    $reason ?? 'Order refunded by admin.',
                    $admin?->id,
                );
            });

        return $transitioned->fresh(['items']);
    }

    /**
     * Resolve a customer dispute by approving a full refund.
     *
     * @throws OrderDisputeException when the dispute is already concluded
     */
    public function resolveDisputeWithRefund(
        Dispute $dispute,
        User $admin,
        ?string $adminNotes = null,
        bool $force = true,
    ): Dispute {
        if (! in_array($dispute->status, ['pending', 'under_review'], true)) {
            throw new OrderDisputeException('Dispute has already been resolved or closed.', 409);
        }

        $order = $dispute->order;
        $reason = $adminNotes ?? $dispute->reason;

        $this->refundOrder($order, $reason, $force, $admin);

        return $this->disputes->updateResolution(
            $dispute,
            'resolved_refunded',
            $adminNotes ?? 'Dispute approved and order refunded.',
            $admin->id,
        )->load(['order.items', 'user', 'resolver']);
    }

    /**
     * Reject a dispute with mandatory administrator explanation.
     *
     * @throws OrderDisputeException when the dispute is already concluded
     */
    public function rejectDispute(Dispute $dispute, User $admin, string $adminNotes): Dispute
    {
        if (! in_array($dispute->status, ['pending', 'under_review'], true)) {
            throw new OrderDisputeException('Dispute has already been resolved or closed.', 409);
        }

        return $this->disputes->updateResolution(
            $dispute,
            'rejected',
            $adminNotes,
            $admin->id,
        )->load(['order.items', 'user', 'resolver']);
    }

    /** Paginate disputes filed by a specific customer. */
    public function forCustomer(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->disputes->forUser($userId, $perPage);
    }

    /** Paginate disputes for admin triage. */
    public function forAdmin(?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->disputes->allForAdmin(['status' => $status], $perPage);
    }

    /** Paginate disputes on an order. */
    public function forOrder(int $orderId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->disputes->forOrder($orderId, $perPage);
    }

    /** Find a dispute by ID. */
    public function find(int $id): ?Dispute
    {
        return $this->disputes->find($id);
    }
}
