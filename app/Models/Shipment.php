<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Shipment extends Model
{
    /** @use HasFactory<ShipmentFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['order_item_id', 'tracking_number', 'status', 'shipped_at', 'delivered_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /** The order line this shipment belongs to. */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** The order behind the shipped line. */
    public function order(): HasOneThrough
    {
        return $this->hasOneThrough(Order::class, OrderItem::class, 'id', 'id', 'order_item_id', 'order_id');
    }

    /** Determine whether the shipment has been delivered. */
    public function isDelivered(): bool
    {
        return $this->status === ShipmentStatus::DELIVERED;
    }
}
