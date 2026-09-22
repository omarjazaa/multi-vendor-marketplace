<?php

namespace App\Models;

use Database\Factories\ProductImageFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    /** @use HasFactory<ProductImageFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'product_id', 'path', 'is_primary',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    /** Get the product that owns this image. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Resolve the publicly reachable URL for the stored file.
     *
     * The stored path stays relative to the media disk so that moving the
     * catalog to object storage (S3) requires no data migration.
     */
    protected function url(): Attribute
    {
        return Attribute::get(
            fn (): string => Storage::disk(config('marketplace.products.images.disk'))->url($this->path),
        );
    }
}
