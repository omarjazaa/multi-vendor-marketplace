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
    protected $fillable = ['user_id', 'status', 'payment_method', 'total_price'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total_price' => 'decimal:2',
        ];
    }

    /** Get the customer who placed the order. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Get the snapshot lines belonging to this order. */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
