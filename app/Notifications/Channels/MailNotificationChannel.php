<?php

namespace App\Notifications\Channels;

use App\Mail\OrderNotificationMail;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails the notification through a queued Mailable built from the payload.
 *
 * Queuing is controlled by marketplace.notifications.queue_mail: the queued
 * path defers delivery to the queue worker, the immediate path sends inline
 * (handy for local development). Either way a transport failure is reported
 * as a failed result, never thrown — the order is already committed.
 */
final class MailNotificationChannel implements NotificationChannelInterface
{
    public function channel(): string
    {
        return 'mail';
    }

    public function send(object $notifiable, NotificationPayload $payload): ChannelResult
    {
        $email = $this->resolveEmail($notifiable);

        if ($email === null) {
            return ChannelResult::failed('mail', 'No email address is available for this recipient.');
        }

        $queued = (bool) config('marketplace.notifications.queue_mail', true);

        try {
            $mail = Mail::to($email);

            if ($queued) {
                $mail->queue(new OrderNotificationMail($payload));
            } else {
                $mail->send(new OrderNotificationMail($payload));
            }
        } catch (Throwable $exception) {
            return ChannelResult::failed('mail', $exception->getMessage());
        }

        return ChannelResult::delivered('mail', null, $queued ? 'Order email queued.' : 'Order email sent.');
    }

    private function resolveEmail(object $notifiable): ?string
    {
        $email = $notifiable->email ?? null;

        if (is_string($email) && $email !== '') {
            return $email;
        }

        if (method_exists($notifiable, 'routeNotificationForMail')) {
            $routed = $notifiable->routeNotificationForMail();

            if (is_string($routed) && $routed !== '') {
                return $routed;
            }
        }

        return null;
    }
}
