<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'store_id', 'category_id', 'name', 'slug', 'description', 'base_price', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['base_price' => 'decimal:2', 'is_active' => 'boolean'];
    }

    /** Get the owning store. */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** Get the product category. */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
