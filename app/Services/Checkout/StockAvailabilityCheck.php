<?php

namespace App\Services\Checkout;

use App\Exceptions\InsufficientStockException;
use App\Repositories\Contracts\InventoryRepositoryInterface;

/**
 * First link of the checkout chain: fail fast when a line outran live stock.
 *
 * This is a pre-flight read so the customer gets a clean 409 with the real
 * available count before any write happens. The guarded decrement inside
 * the checkout transaction stays the single authority on stock, so a race
 * between this check and the reservation is still caught and rolled back.
 */
class StockAvailabilityCheck extends CheckoutValidationHandler
{
    public function __construct(private readonly InventoryRepositoryInterface $inventories) {}

    /** @throws InsufficientStockException when a line asks for more than is available */
    protected function validate(CheckoutContext $context): void
    {
        foreach ($context->lines as $line) {
            $available = $this->inventories->firstOrCreateForProduct($line->product)->quantity;

            if ($available < $line->quantity) {
                throw new InsufficientStockException($available, $line->product->name);
            }
        }
    }
}
