<?php

namespace App\Models;

use Database\Factories\CartItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    /** @use HasFactory<CartItemFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['cart_id', 'product_id', 'quantity', 'unit_price'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price' => 'decimal:2'];
    }

    /** Get the cart that holds this line item. */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /** Get the product referenced by this line item. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
