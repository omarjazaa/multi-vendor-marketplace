<?php

namespace App\Notifications;

use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeVendorNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Store $store)
    {
        $this->queue = 'notifications';
    }

    /**
     * Send the welcome notification through email.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the vendor approval welcome email.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your marketplace store is approved')
            ->greeting("Welcome to the marketplace, {$notifiable->name}!")
            ->line("Your store, {$this->store->name}, has been approved.");
    }
}
