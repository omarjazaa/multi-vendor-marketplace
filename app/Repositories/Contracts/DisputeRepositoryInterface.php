<?php

namespace App\Repositories\Contracts;

use App\Models\Dispute;
use App\Models\Order;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface DisputeRepositoryInterface
{
    /** Create a new dispute on an order. */
    public function create(Order $order, User $user, string $reason): Dispute;

    /** Find a dispute by ID with relationships. */
    public function find(int $id): ?Dispute;

    /** Paginate disputes filed by a specific customer. */
    public function forUser(int $userId, int $perPage = 15): LengthAwarePaginator;

    /** Paginate disputes for an order. */
    public function forOrder(int $orderId, int $perPage = 15): LengthAwarePaginator;

    /** Paginate all disputes for admin view, optionally filtered by status. */
    public function allForAdmin(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /** Update dispute status, resolution notes, and resolver admin. */
    public function updateResolution(Dispute $dispute, string $status, ?string $adminNotes = null, ?int $adminId = null): Dispute;

    /** Check if an order already has an active pending or under_review dispute. */
    public function hasActiveDispute(int $orderId): bool;
}
