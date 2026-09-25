<?php

namespace App\Policies;

use App\Models\Cart;
use App\Models\User;

class CartPolicy
{
    /** Determine whether the user may view the cart. */
    public function view(User $user, Cart $cart): bool
    {
        return $this->ownsCart($user, $cart);
    }

    /** Determine whether the user may modify the cart and its items. */
    public function update(User $user, Cart $cart): bool
    {
        return $this->ownsCart($user, $cart);
    }

    /** Determine whether the user may empty or clear the cart. */
    public function delete(User $user, Cart $cart): bool
    {
        return $this->ownsCart($user, $cart);
    }

    private function ownsCart(User $user, Cart $cart): bool
    {
        return $cart->user_id === $user->id;
    }
}
