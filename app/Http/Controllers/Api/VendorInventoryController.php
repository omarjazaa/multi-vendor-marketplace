<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateInventoryRequest;
use App\Http\Resources\InventoryResource;
use App\Http\Traits\ApiResponse;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class VendorInventoryController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly InventoryService $inventory) {}

    public function show(Product $product): JsonResponse
    {
        Gate::authorize('update', $product);

        return $this->successResponse([
            'inventory' => InventoryResource::make($this->inventory->forProduct($product)),
        ]);
    }

    public function update(UpdateInventoryRequest $request, Product $product): JsonResponse
    {
        Gate::authorize('update', $product);

        return $this->successResponse([
            'inventory' => InventoryResource::make($this->inventory->update($product, $request->validated())),
        ], 'Inventory updated.');
    }
}
