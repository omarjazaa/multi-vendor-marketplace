<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Traits\ApiResponse;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CategoryService $categories) {}

    public function index(): JsonResponse
    {
        return $this->successResponse([
            'categories' => CategoryResource::collection($this->categories->all()),
        ]);
    }
}
