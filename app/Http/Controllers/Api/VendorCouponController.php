<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCouponRequest;
use App\Http\Resources\CouponResource;
use App\Http\Traits\ApiResponse;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;

class VendorCouponController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CouponService $coupons) {}

    /** Create a platform coupon (role:vendor). */
    public function store(StoreCouponRequest $request): JsonResponse
    {
        $coupon = $this->coupons->create($request->validated(), $request->user());

        return $this->successResponse(
            ['coupon' => CouponResource::make($coupon)],
            'Coupon created.',
            201,
        );
    }
}
