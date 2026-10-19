<?php

namespace App\Repositories;

use App\Models\Dispute;
use App\Models\Order;
use App\Models\User;
use App\Repositories\Contracts\DisputeRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentDisputeRepository implements DisputeRepositoryInterface
{
    public function create(Order $order, User $user, string $reason): Dispute
    {
        return Dispute::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'reason' => $reason,
            'status' => 'pending',
        ]);
    }

    public function find(int $id): ?Dispute
    {
        return Dispute::with(['order.items', 'user', 'resolver'])->find($id);
    }

    public function forUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return Dispute::query()
            ->with(['order.items'])
            ->where('user_id', $userId)
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function forOrder(int $orderId, int $perPage = 15): LengthAwarePaginator
    {
        return Dispute::query()
            ->with(['user', 'resolver'])
            ->where('order_id', $orderId)
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function allForAdmin(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Dispute::query()
            ->with(['order.items', 'user', 'resolver'])
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status),
            )
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function updateResolution(
        Dispute $dispute,
        string $status,
        ?string $adminNotes = null,
        ?int $adminId = null,
    ): Dispute {
        $dispute->update([
            'status' => $status,
            'admin_notes' => $adminNotes,
            'resolved_by' => $adminId,
            'resolved_at' => now(),
        ]);

        return $dispute;
    }

    public function hasActiveDispute(int $orderId): bool
    {
        return Dispute::where('order_id', $orderId)
            ->whereIn('status', ['pending', 'under_review'])
            ->exists();
    }
}
