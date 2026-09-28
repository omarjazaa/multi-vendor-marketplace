<?php

namespace App\Repositories;

use App\Models\Coupon;
use App\Repositories\Contracts\CouponRepositoryInterface;

class EloquentCouponRepository implements CouponRepositoryInterface
{
    public function create(array $attributes): Coupon
    {
        return Coupon::create($attributes);
    }

    public function findByCode(string $code): ?Coupon
    {
        // Codes are stored uppercase, so normalising the lookup keeps the
        // search case-insensitive on case-sensitive engines like sqlite.
        return Coupon::query()
            ->where('code', strtoupper(trim($code)))
            ->first();
    }
}
