<?php

namespace App\Listeners;

use App\Events\VendorApproved;
use App\Notifications\WelcomeVendorNotification;

class SendVendorWelcomeNotification
{
    /**
     * Notify the vendor after their store application is approved.
     */
    public function handle(VendorApproved $event): void
    {
        $event->store->vendorProfile->user->notify(new WelcomeVendorNotification($event->store));
    }
}
