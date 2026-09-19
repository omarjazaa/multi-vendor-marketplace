<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\StoreApplicationAlreadyReviewed;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Store;
use App\Services\VendorApprovalService;
use Illuminate\Http\JsonResponse;

class AdminStoreController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly VendorApprovalService $approval) {}

    /**
     * Approve a pending vendor store application.
     */
    public function approve(Store $store): JsonResponse
    {
        try {
            $store = $this->approval->approve($store);
        } catch (StoreApplicationAlreadyReviewed $exception) {
            return $this->errorResponse($exception->getMessage(), status: 409);
        }

        return $this->successResponse(['store' => $store], 'Store application approved.');
    }

    /**
     * Reject a pending vendor store application.
     */
    public function reject(Store $store): JsonResponse
    {
        try {
            $store = $this->approval->reject($store);
        } catch (StoreApplicationAlreadyReviewed $exception) {
            return $this->errorResponse($exception->getMessage(), status: 409);
        }

        return $this->successResponse(['store' => $store], 'Store application rejected.');
    }
}
