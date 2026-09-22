<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Collection;

interface ProductImageRepositoryInterface
{
    /**
     * Return the product images ordered from oldest to newest.
     *
     * @return Collection<int, ProductImage>
     */
    public function forProduct(Product $product): iterable;

    /** @param array<string, mixed> $attributes */
    public function create(Product $product, array $attributes): ProductImage;

    /** @param array<string, mixed> $attributes */
    public function update(ProductImage $image, array $attributes): ProductImage;

    public function delete(ProductImage $image): void;
}
