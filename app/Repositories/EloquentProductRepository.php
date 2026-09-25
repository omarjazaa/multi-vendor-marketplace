<?php

namespace App\Repositories;

use App\Enums\StoreStatus;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentProductRepository implements ProductRepositoryInterface
{
    /** @return Collection<int, Product> */
    public function forVendor(int $userId): iterable
    {
        return Product::whereHas('store.vendorProfile', fn ($query) => $query->where('user_id', $userId))
            ->with(['store', 'category', 'images', 'inventory'])
            ->latest()
            ->get();
    }

    /**
     * Paginate the products that are publicly visible in the catalog.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginateVisible(array $filters, int $perPage, string $sort): LengthAwarePaginator
    {
        $query = $this->visibleQuery();

        if (! empty($filters['search'])) {
            $term = (string) $filters['search'];
            $query->where(fn (Builder $inner): Builder => $inner
                ->where('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%"));
        }

        if (! empty($filters['category'])) {
            $query->whereHas('category', fn (Builder $inner): Builder => $inner->where('slug', $filters['category']));
        }

        if (isset($filters['min_price'])) {
            $query->where('base_price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('base_price', '<=', $filters['max_price']);
        }

        if (! empty($filters['availability'])) {
            match ($filters['availability']) {
                'in_stock' => $query->whereHas('inventory', fn (Builder $inner): Builder => $inner
                    ->where('quantity', '>', 0)
                    ->whereColumn('inventories.quantity', '>', 'inventories.low_stock_threshold')),
                'low_stock' => $query->whereHas('inventory', fn (Builder $inner): Builder => $inner
                    ->where('quantity', '>', 0)
                    ->whereColumn('inventories.quantity', '<=', 'inventories.low_stock_threshold')),
                // No inventory row yet still reads as an empty shelf.
                'out_of_stock' => $query->where(fn (Builder $inner): Builder => $inner
                    ->whereHas('inventory', fn (Builder $stock): Builder => $stock->where('quantity', 0))
                    ->orWhereDoesntHave('inventory')),
                default => $query,
            };
        }

        $sorts = (array) config('marketplace.catalog.sorts');
        $defaultSort = (string) config('marketplace.catalog.default_sort');

        /** @var array{0: string, 1: string} $ordering */
        $ordering = $sorts[$sort] ?? $sorts[$defaultSort] ?? ['created_at', 'desc'];

        return $query
            ->orderBy($ordering[0], $ordering[1])
            ->orderBy('id', $ordering[1])
            ->paginate($perPage)
            ->withQueryString();
    }

    /** Return a publicly visible product, or abort with a 404. */
    public function findVisible(int $id): Product
    {
        return $this->visibleQuery()->whereKey($id)->firstOrFail();
    }

    /** Scope a query to catalog-ready products: active and from an approved store. */
    private function visibleQuery(): Builder
    {
        return Product::query()
            ->where('is_active', true)
            ->whereHas('store', fn (Builder $query): Builder => $query->where('status', StoreStatus::APPROVED))
            ->with(['store', 'category', 'images', 'inventory']);
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Product
    {
        return Product::create($attributes)->load(['store', 'category', 'images']);
    }

    /** @param array<string, mixed> $attributes */
    public function update(Product $product, array $attributes): Product
    {
        $product->update($attributes);

        return $product->fresh(['store', 'category', 'images', 'inventory']);
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }
}
