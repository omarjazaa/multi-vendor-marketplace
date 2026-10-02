<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Notifications\Channels\ChannelResult;
use App\Notifications\Channels\NotificationChannelFactory;
use App\Notifications\Channels\NotificationChannelInterface;
use App\Notifications\Channels\NotificationPayload;
use App\Services\Checkout\CheckoutOutcome;
use Illuminate\Support\Collection;
use Throwable;

/** Delivers order notifications after checkout commits; failures are reported, never thrown. */
final class OrderNotificationService
{
    public function __construct(private readonly NotificationChannelFactory $channels) {}

    /** @param  Collection<int, Order>  $orders */
    public function notifyCheckout(User $customer, Collection $orders, CheckoutOutcome $outcome): array
    {
        $customerResult = $this->deliver(
            $customer,
            (string) config('marketplace.notifications.customer_channel', 'database'),
            NotificationPayload::make(
                'customer_confirmation',
                $this->customerSubject($orders),
                sprintf('Thanks %s — your order is confirmed.', $customer->name),
                [
                    'customer' => ['id' => $customer->id, 'name' => $customer->name],
                    'orders' => $this->summaries($orders),
                    'payment' => $outcome->paymentPayload(),
                ],
            ),
        );

        $vendors = [];

        foreach ($orders as $order) {
            $owner = $this->resolveVendorOwner($order);

            $vendors[(int) $order->id] = $owner === null
                ? null
                : $this->deliver(
                    $owner,
                    (string) config('marketplace.notifications.vendor_channel', 'database'),
                    NotificationPayload::make(
                        'vendor_new_order',
                        sprintf('New order #%d needs fulfilment', (int) $order->id),
                        sprintf('Order #%d from %s is ready to fulfil.', (int) $order->id, $customer->name),
                        [
                            'order_id' => (int) $order->id,
                            'buyer' => ['id' => $customer->id, 'name' => $customer->name],
                            'orders' => $this->summaries(new Collection([$order])),
                            'payment' => $outcome->paymentPayload(),
                        ],
                    ),
                );
        }

        return ['customer' => $customerResult, 'vendors' => $vendors];
    }

    private function deliver(object $notifiable, string $channel, NotificationPayload $payload): ?ChannelResult
    {
        if (trim($channel) === '') {
            return null;
        }

        try {
            $resolved = $this->resolve($channel);
        } catch (Throwable $exception) {
            return ChannelResult::failed($channel, $exception->getMessage());
        }

        try {
            return $resolved->send($notifiable, $payload);
        } catch (Throwable $exception) {
            return ChannelResult::failed($resolved->channel(), $exception->getMessage());
        }
    }

    private function resolve(string $channel): NotificationChannelInterface
    {
        return $this->channels->make($channel);
    }

    private function resolveVendorOwner(Order $order): ?User
    {
        try {
            // Eager-load the full chain so owner, profile, store and order
            // data are consistent within one request lifecycle.
            $order->loadMissing(['store.vendorProfile.user']);
            $owner = $order->store?->vendorProfile?->user;
        } catch (Throwable) {
            return null;
        }

        return $owner instanceof User ? $owner : null;
    }

    /** @param  Collection<int, Order>  $orders */
    private function customerSubject(Collection $orders): string
    {
        return $orders->count() > 1
            ? sprintf('Your %d orders are confirmed', $orders->count())
            : sprintf('Your order #%d is confirmed', (int) $orders->first()?->id);
    }

    /** @param  Collection<int, Order>  $orders */
    private function summaries(Collection $orders): array
    {
        return $orders->map(fn (Order $order): array => [
            'order_id' => (int) $order->id,
            'store_id' => $order->store_id === null ? null : (int) $order->store_id,
            'status' => $order->status->value,
            'payment_method' => $order->payment_method,
            'payment_reference' => $order->payment_reference,
            'discount' => (string) $order->discount,
            'tax' => (string) $order->tax,
            'total_price' => (string) $order->total_price,
            'items' => $order->items->map(fn ($item): array => [
                'product_id' => (int) $item->product_id,
                'name' => (string) $item->name,
                'unit_price' => (string) $item->unit_price,
                'quantity' => (int) $item->quantity,
            ])->all(),
        ])->values()->all();
    }
}
