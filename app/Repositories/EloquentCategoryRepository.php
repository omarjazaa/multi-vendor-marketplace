<?php

namespace App\Repositories;

use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentCategoryRepository implements CategoryRepositoryInterface
{
    /** @return Collection<int, Category> */
    public function all(): iterable
    {
        return Category::whereNull('parent_id')->with('children')->orderBy('name')->get();
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Category
    {
        return Category::create($attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function update(Category $category, array $attributes): Category
    {
        $category->update($attributes);

        return $category->fresh();
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }
}
