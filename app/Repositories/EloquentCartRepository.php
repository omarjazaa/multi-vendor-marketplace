<?php

namespace App\Repositories;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Repositories\Contracts\CartRepositoryInterface;

class EloquentCartRepository implements CartRepositoryInterface
{
    /** Return the user's cart, opening an empty one when missing. */
    public function forUser(int $userId): Cart
    {
        return Cart::firstOrCreate(['user_id' => $userId]);
    }

    /** Return the cart line for a product, when the cart holds it. */
    public function itemFor(Cart $cart, int $productId): ?CartItem
    {
        return $cart->items()->where('product_id', $productId)->first();
    }

    /** Add a product to the cart, snapshotting its current base price. */
    public function createItem(Cart $cart, Product $product, int $quantity): CartItem
    {
        return $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $product->base_price,
        ]);
    }

    /** Persist a new quantity for an existing cart line. */
    public function changeQuantity(CartItem $item, int $quantity): CartItem
    {
        $item->update(['quantity' => $quantity]);

        return $item->refresh();
    }

    public function removeItem(CartItem $item): void
    {
        $item->delete();
    }

    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
    }
}
