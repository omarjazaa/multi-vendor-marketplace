<?php

namespace App\Services;

use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;

class CategoryService
{
    public function __construct(private readonly CategoryRepositoryInterface $categories) {}

    /** Return hierarchical category roots. */
    public function all(): iterable
    {
        return $this->categories->all();
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Category
    {
        return $this->categories->create($attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function update(Category $category, array $attributes): Category
    {
        return $this->categories->update($category, $attributes);
    }

    public function delete(Category $category): void
    {
        $this->categories->delete($category);
    }
}
