<?php

namespace App\Repositories;

use App\Models\Product;
use App\Models\ProductImage;
use App\Repositories\Contracts\ProductImageRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentProductImageRepository implements ProductImageRepositoryInterface
{
    /** @return Collection<int, ProductImage> */
    public function forProduct(Product $product): iterable
    {
        return $product->images()->orderBy('id')->get();
    }

    /** @param array<string, mixed> $attributes */
    public function create(Product $product, array $attributes): ProductImage
    {
        return $product->images()->create($attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function update(ProductImage $image, array $attributes): ProductImage
    {
        $image->update($attributes);

        return $image->fresh();
    }

    public function delete(ProductImage $image): void
    {
        $image->delete();
    }
}
