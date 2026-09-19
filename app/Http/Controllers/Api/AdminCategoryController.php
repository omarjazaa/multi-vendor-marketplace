<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;

class AdminCategoryController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CategoryService $categories) {}

    public function index(): JsonResponse
    {
        return $this->successResponse(['categories' => $this->categories->all()]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        return $this->successResponse(
            ['category' => $this->categories->create($request->validated())],
            'Category created.',
            201,
        );
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        return $this->successResponse([
            'category' => $this->categories->update($category, $request->validated()),
        ], 'Category updated.');
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->categories->delete($category);

        return response()->json(status: 204);
    }
}
