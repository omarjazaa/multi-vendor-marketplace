<?php

namespace App\Models;

use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'product_id',
        'order_id',
        'rating',
        'comment',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    /** The customer who authored this review. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The product being reviewed. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** The delivered order that verified this purchase. */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
