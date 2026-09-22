<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductImageRequest;
use App\Http\Resources\ProductImageResource;
use App\Http\Traits\ApiResponse;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

class VendorProductImageController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ProductImageService $images) {}

    public function index(Product $product): JsonResponse
    {
        Gate::authorize('update', $product);

        return $this->successResponse([
            'images' => ProductImageResource::collection($this->images->forProduct($product)),
        ]);
    }

    public function store(StoreProductImageRequest $request, Product $product): JsonResponse
    {
        Gate::authorize('update', $product);

        /** @var array<int, UploadedFile> $files */
        $files = $request->file('images', []);

        return $this->successResponse(
            ['images' => ProductImageResource::collection($this->images->store($product, $files))],
            'Product images uploaded.',
            201,
        );
    }

    public function destroy(Product $product, ProductImage $image): JsonResponse
    {
        Gate::authorize('update', $product);
        $this->images->delete($product, $image);

        return response()->json(status: 204);
    }
}
