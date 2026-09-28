<?php

namespace App\Repositories\Contracts;

use App\Models\Coupon;

interface CouponRepositoryInterface
{
    /** Persist a new coupon from the given attributes. */
    public function create(array $attributes): Coupon;

    /** Find a coupon by its (case-insensitive) code, or null when missing. */
    public function findByCode(string $code): ?Coupon;
}
