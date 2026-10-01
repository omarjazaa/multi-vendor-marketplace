<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['user_id', 'store_id', 'status', 'payment_method', 'payment_reference', 'discount', 'tax', 'total_price'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    /** Get the customer who placed the order. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Get the vendor store this split order belongs to (null for legacy rows). */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** Get the snapshot lines belonging to this order. */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
