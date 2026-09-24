<?php

namespace App\Models;

use Database\Factories\InventoryFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    /** @use HasFactory<InventoryFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'product_id', 'quantity', 'low_stock_threshold',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity' => 'integer', 'low_stock_threshold' => 'integer'];
    }

    /** Get the product this stock record belongs to. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Whether remaining stock sits at or below the alert threshold but is not empty.
     *
     * An empty stock record is reported through isOutOfStock() instead so that a
     * vendor sees exactly one call to action per product.
     */
    protected function isLowStock(): Attribute
    {
        return Attribute::get(fn (): bool => $this->quantity > 0 && $this->quantity <= $this->low_stock_threshold);
    }

    /** Whether the product is completely out of stock and cannot be ordered. */
    protected function isOutOfStock(): Attribute
    {
        return Attribute::get(fn (): bool => $this->quantity <= 0);
    }
}
