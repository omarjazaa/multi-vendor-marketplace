<?php

namespace App\Repositories\Contracts;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

interface CategoryRepositoryInterface
{
    /** @return Collection<int, Category> */
    public function all(): iterable;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Category;

    /** @param array<string, mixed> $attributes */
    public function update(Category $category, array $attributes): Category;

    public function delete(Category $category): void;
}
