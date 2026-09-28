<?php

namespace App\Models;

use App\Enums\DiscountType;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'code', 'type', 'value', 'expires_at', 'usage_limit',
        'used_count', 'min_order_amount', 'created_by',
    ];

    /**
     * Mirror the column default so a freshly created coupon serialises its
     * zero count before the row is ever re-read from the database.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'used_count' => 0,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'value' => 'decimal:2',
            'expires_at' => 'datetime',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'min_order_amount' => 'decimal:2',
        ];
    }

    /** Get the admin or vendor that created this coupon. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Explain why this coupon cannot be applied to the given amount,
     * or null when it is eligible. Keeps the eligibility rules in one
     * place shared by the pricing decorator and any future checkout use.
     */
    public function rejectionReasonFor(float $amount): ?string
    {
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return 'This coupon has expired.';
        }

        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return 'This coupon has reached its usage limit.';
        }

        if ($this->min_order_amount !== null && $amount < (float) $this->min_order_amount) {
            return sprintf(
                'A minimum order of %.2f is required to use this coupon.',
                (float) $this->min_order_amount,
            );
        }

        return null;
    }
}
