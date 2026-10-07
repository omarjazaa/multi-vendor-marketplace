<?php

namespace App\Listeners;

use App\Events\OrderPlacing;
use App\Exceptions\InsufficientStockException;
use App\Repositories\Contracts\InventoryRepositoryInterface;

/**
 * Reserves stock for every cart line — the "reduce inventory" listener of
 * the Day 20 observer set, deliberately synchronous.
 *
 * It runs inside the checkout transaction (OrderPlacing fires there on
 * purpose), so the guarded decrement keeps the exact semantics of the
 * inline loop it replaced: the WHERE guard and the decrement are one
 * UPDATE, and a lost race throws InsufficientStockException which rolls
 * the whole order back and surfaces as the same 409 the pre-flight check
 * produces. Nothing about a committed order can leave the shelf negative.
 *
 * Never make this listener queued: a job pushed before the commit would
 * run outside the transaction and could no longer undo the order it just
 * failed to reserve for.
 */
class ReduceInventory
{
    public function __construct(private readonly InventoryRepositoryInterface $inventories) {}

    /**
     * @throws InsufficientStockException when a line lost the race for the last units
     */
    public function handle(OrderPlacing $event): void
    {
        foreach ($event->lines as $line) {
            if (! $this->inventories->decrementQuantity($line->product, $line->quantity)) {
                $available = $this->inventories->firstOrCreateForProduct($line->product)->quantity;

                throw new InsufficientStockException($available, $line->product->name);
            }
        }
    }
}
