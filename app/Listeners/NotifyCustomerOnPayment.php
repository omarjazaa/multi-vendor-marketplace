<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Services\OrderNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells the customer their money was received (Day 20 observer set).
 *
 * Queued, so the paid transition is written first and delivery happens
 * outside the request; under the sync test driver it still lands within
 * the same tick. Delivery failures are reported, never thrown — a broken
 * channel must not fail the payment transition that triggered it, which
 * mirrors the containment contract SendOrderNotifications established on
 * Day 18.
 */
class NotifyCustomerOnPayment implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(private readonly OrderNotificationService $notifications) {}

    public function handle(OrderPaid $event): void
    {
        try {
            $result = $this->notifications->notifyPaymentReceived($event->order);
        } catch (Throwable $exception) {
            Log::warning('Payment-received notification failed without a delivery result.', [
                'order_id' => (int) $event->order->id,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        if ($result !== null && ! $result->delivered) {
            Log::warning('Payment-received notification delivery failed.', [
                'order_id' => (int) $event->order->id,
                'channel' => $result->channel,
                'error' => $result->message,
            ]);
        }
    }
}
