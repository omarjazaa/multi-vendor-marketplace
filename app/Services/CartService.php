<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Repositories\Contracts\CartRepositoryInterface;
use App\Repositories\Contracts\InventoryRepositoryInterface;

class CartService
{
    public function __construct(
        private readonly CartRepositoryInterface $carts,
        private readonly InventoryRepositoryInterface $inventories,
    ) {}

    /** Return the user's cart, opening an empty one when missing. */
    public function forUser(int $userId): Cart
    {
        return $this->carts->forUser($userId);
    }

    /**
     * Add a product to the cart, merging into an existing line when present.
     *
     * @throws InsufficientStockException when the resulting quantity exceeds stock
     */
    public function addItem(Cart $cart, Product $product, int $quantity): CartItem
    {
        $inventory = $this->inventories->firstOrCreateForProduct($product);
        $existing = $this->carts->itemFor($cart, $product->id);

        $target = ($existing?->quantity ?? 0) + $quantity;
        $this->guardStock($inventory->quantity, $target);

        if ($existing !== null) {
            return $this->carts->changeQuantity($existing, $target);
        }

        return $this->carts->createItem($cart, $product, $quantity);
    }

    /**
     * Replace the quantity of an existing cart line.
     *
     * @throws InsufficientStockException when the requested quantity exceeds stock
     */
    public function updateQuantity(CartItem $item, int $quantity): CartItem
    {
        $inventory = $this->inventories->firstOrCreateForProduct($item->product);

        $this->guardStock($inventory->quantity, $quantity);

        return $this->carts->changeQuantity($item, $quantity);
    }

    public function removeItem(CartItem $item): void
    {
        $this->carts->removeItem($item);
    }

    public function clear(Cart $cart): void
    {
        $this->carts->clear($cart);
    }

    /** Enforce the stock ceiling coming from the Day 9 inventory layer. */
    private function guardStock(int $available, int $requested): void
    {
        if ($requested > $available) {
            throw new InsufficientStockException($available);
        }
    }
}
