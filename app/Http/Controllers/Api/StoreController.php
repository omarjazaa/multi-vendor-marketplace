<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\StoreApplicationAlreadyExists;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApplyForStoreRequest;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use App\Services\VendorStoreService;
use Illuminate\Http\JsonResponse;

class StoreController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly VendorStoreService $stores) {}

    /**
     * Submit a vendor's store application for later admin review.
     */
    public function apply(ApplyForStoreRequest $request): JsonResponse
    {
        /** @var User $vendor */
        $vendor = $request->user();

        try {
            $store = $this->stores->apply($vendor, $request->validated());
        } catch (StoreApplicationAlreadyExists $exception) {
            return $this->errorResponse($exception->getMessage(), status: 409);
        }

        return $this->successResponse(['store' => $store], 'Store application submitted.', 201);
    }
}
