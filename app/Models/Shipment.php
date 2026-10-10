<?php

namespace App\Models;

use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
            'status' => 'string',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /** The order line this shipment belongs to. */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** The order behind the shipped line — resolved through the item snapshot. */
    public function order(): ?BelongsTo
    {
        return $this->orderItem?->order();
    }
}
