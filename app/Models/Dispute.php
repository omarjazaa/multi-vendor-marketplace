<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dispute extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'order_id',
        'user_id',
        'reason',
        'status',
        'admin_notes',
        'resolved_by',
        'resolved_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    /** The order being disputed. */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** The customer who filed the dispute. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The administrator who resolved or rejected the dispute. */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** Check if dispute is still awaiting review. */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /** Check if dispute was resolved with a refund. */
    public function isResolvedWithRefund(): bool
    {
        return $this->status === 'resolved_refunded';
    }
}
