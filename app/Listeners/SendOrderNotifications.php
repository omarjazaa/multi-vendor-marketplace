<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Services\OrderNotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers the OrderPlaced notifications after checkout has committed.
 *
 * Runs synchronously in the request lifecycle but NEVER inside the order
 * transaction: a failed channel reports itself through the notification
 * service and can never roll back a placed order. A buggy listener is
 * equally contained — any unexpected throw is logged and swallowed so the
 * 201 checkout response stands.
 */
class SendOrderNotifications
{
    public function __construct(private readonly OrderNotificationService $notifications) {}

    public function handle(OrderPlaced $event): void
    {
        try {
            $results = $this->notifications->notifyCheckout($event->customer, $event->orders, $event->outcome);
        } catch (Throwable $exception) {
            Log::warning('Order notifications failed without delivery results.', [
                'customer_id' => $event->customer->id,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        foreach ($this->failures($results) as $failure) {
            Log::warning('Order notification delivery failed.', $failure);
        }
    }

    /** @return list<array<string, mixed>> context for every failed delivery */
    private function failures(array $results): array
    {
        $failures = [];
        $customer = $results['customer'] ?? null;

        if ($customer !== null && ! $customer->delivered) {
            $failures[] = [
                'audience' => 'customer',
                'channel' => $customer->channel,
                'error' => $customer->message,
            ];
        }

        foreach ((array) ($results['vendors'] ?? []) as $orderId => $result) {
            if ($result !== null && ! $result->delivered) {
                $failures[] = [
                    'audience' => 'vendor',
                    'order_id' => $orderId,
                    'channel' => $result->channel,
                    'error' => $result->message,
                ];
            }
        }

        return $failures;
    }
}
