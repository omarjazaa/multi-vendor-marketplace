<?php

namespace App\Events;

use App\Models\Store;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VendorApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Store $store) {}
}
