<?php

namespace App\Listeners;

use App\Events\OrderShipped;
use App\Services\OrderNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells the customer their order left the warehouse (Day 20 observer set).
 *
 * Queued and failure-tolerant for the same reasons as
 * NotifyCustomerOnPayment: the shipment transition stands regardless of
 * what the notification channel does.
 */
class NotifyCustomerOnShipment implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(private readonly OrderNotificationService $notifications) {}

    public function handle(OrderShipped $event): void
    {
        try {
            $result = $this->notifications->notifyShipped($event->order);
        } catch (Throwable $exception) {
            Log::warning('Shipment notification failed without a delivery result.', [
                'order_id' => (int) $event->order->id,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        if ($result !== null && ! $result->delivered) {
            Log::warning('Shipment notification delivery failed.', [
                'order_id' => (int) $event->order->id,
                'channel' => $result->channel,
                'error' => $result->message,
            ]);
        }
    }
}
