<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['order_id', 'product_id', 'name', 'unit_price', 'quantity'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2', 'quantity' => 'integer'];
    }

    /** Get the order that owns this line. */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Get the product referenced when the order was placed. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
