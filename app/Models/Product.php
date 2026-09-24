<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    /** Get the gallery images of this product, oldest first. */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('id');
    }

    /** Get the stock record tracked for this product. */
    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class);
    }
}
