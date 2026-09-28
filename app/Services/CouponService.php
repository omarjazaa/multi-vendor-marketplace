<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\User;
use App\Repositories\Contracts\CouponRepositoryInterface;

class CouponService
{
    public function __construct(private readonly CouponRepositoryInterface $coupons) {}

    /**
     * Create a coupon on behalf of an admin or vendor; payload rules were
     * already enforced by StoreCouponRequest. Codes are normalised once here
     * so storage and lookups always agree on the canonical form.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $creator): Coupon
    {
        return $this->coupons->create([
            ...$attributes,
            'code' => strtoupper((string) $attributes['code']),
            'created_by' => $creator->id,
        ]);
    }
}
