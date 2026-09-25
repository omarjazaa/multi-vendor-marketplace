<?php

namespace App\Repositories\Contracts;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;

interface CartRepositoryInterface
{
    /** Return the user's cart, opening an empty one when missing. */
    public function forUser(int $userId): Cart;

    /** Return the cart line for a product, when the cart holds it. */
    public function itemFor(Cart $cart, int $productId): ?CartItem;

    /** Add a product to the cart, snapshotting its current base price. */
    public function createItem(Cart $cart, Product $product, int $quantity): CartItem;

    /** Persist a new quantity for an existing cart line. */
    public function changeQuantity(CartItem $item, int $quantity): CartItem;

    public function removeItem(CartItem $item): void;

    public function clear(Cart $cart): void;
}
