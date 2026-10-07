<?php

namespace App\Events;

use App\Models\CartItem;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Fired inside the checkout transaction, before the split orders are
 * written — the synchronous phase of the Day 20 observer set.
 *
 * Its one listener, ReduceInventory, performs the guarded stock decrement
 * while the transaction is still open, so a losing race rolls the whole
 * order back exactly like the inline loop it replaced ever since Day 13.
 * That is the deliberate split from OrderPlaced: OrderPlaced fires after
 * the commit carrying the payment outcome (Day 18) and can never undo a
 * sold-out order, so the reservation lives on its own event here instead
 * of being bolted onto a post-commit notification event.
 *
 * A queued listener could never own this decrement: a job pushed before
 * the commit would race the very rows it is trying to reserve.
 */
class OrderPlacing
{
    use Dispatchable, SerializesModels;

    /**
     * @param  Collection<int, CartItem>  $lines  cart lines with their product eager-loaded
     */
    public function __construct(public readonly Collection $lines) {}
}
