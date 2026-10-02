<?php

namespace App\Notifications\Channels;

use Illuminate\Support\Str;
use Throwable;

/**
 * Stores the notification in Laravel's notifications table through the
 * recipient's Notifiable relation.
 *
 * The row keeps the whole payload array, so the customer confirmation (split
 * orders, allocated discount/tax/total, payment outcome) and the vendor
 * alerts stay queryable long after the checkout response is gone.
 */
final class DatabaseNotificationChannel implements NotificationChannelInterface
{
    public function channel(): string
    {
        return 'database';
    }

    public function send(object $notifiable, NotificationPayload $payload): ChannelResult
    {
        if (! method_exists($notifiable, 'notifications')) {
            return ChannelResult::failed('database', 'This recipient cannot receive database notifications.');
        }

        try {
            $notification = $notifiable->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => $payload->kind,
                'data' => $payload->toArray(),
            ]);
        } catch (Throwable $exception) {
            return ChannelResult::failed('database', $exception->getMessage());
        }

        return ChannelResult::delivered('database', (string) $notification->getKey(), 'Order notification stored.');
    }
}
