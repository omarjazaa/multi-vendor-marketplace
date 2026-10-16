<?php

namespace App\Listeners;

use App\Events\VendorApproved;
use App\Notifications\WelcomeVendorNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendVendorWelcomeNotification implements ShouldQueue
{
    /**
     * The name of the queue on which to place the notification job.
     */
    public string $queue = 'notifications';

    /**
     * Notify the vendor after their store application is approved.
     */
    public function handle(VendorApproved $event): void
    {
        $event->store->vendorProfile->user->notify(new WelcomeVendorNotification($event->store));
    }
}
