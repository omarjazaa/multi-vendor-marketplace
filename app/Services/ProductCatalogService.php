<?php

namespace App\Services;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductCatalogService
{
    public function __construct(private readonly ProductRepositoryInterface $products) {}

    /**
     * Paginate the guest facing catalog, applying config driven defaults.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function index(array $filters): LengthAwarePaginator
    {
        return $this->products->paginateVisible(
            filters: $filters,
            perPage: (int) ($filters['per_page'] ?? config('marketplace.catalog.default_per_page')),
            sort: (string) ($filters['sort'] ?? config('marketplace.catalog.default_sort')),
        );
    }

    /** Return a publicly visible product, or abort with a 404. */
    public function findVisible(int $id): Product
    {
        return $this->products->findVisible($id);
    }
}
