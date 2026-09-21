<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class VendorProductController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ProductService $products) {}

    public function index(): JsonResponse
    {
        /** @var User $vendor */
        $vendor = request()->user();

        return $this->successResponse(['products' => $this->products->forVendor($vendor->id)]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $store = Store::findOrFail($request->integer('store_id'));
        Gate::authorize('create', [Product::class, $store]);

        return $this->successResponse(
            ['product' => $this->products->create($request->validated())],
            'Product created.',
            201,
        );
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        Gate::authorize('update', $product);

        return $this->successResponse([
            'product' => $this->products->update($product, $request->validated()),
        ], 'Product updated.');
    }

    public function destroy(Product $product): JsonResponse
    {
        Gate::authorize('delete', $product);
        $this->products->delete($product);

        return response()->json(status: 204);
    }
}
