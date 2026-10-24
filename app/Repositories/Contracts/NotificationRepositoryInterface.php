<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;

interface NotificationRepositoryInterface
{
    /**
     * Get paginated notifications for the specified user, optionally filtering by unread status.
     */
    public function forUser(User $user, ?bool $unreadOnly = null, int $perPage = 15): LengthAwarePaginator;

    /**
     * Count unread notifications for the specified user.
     */
    public function unreadCountForUser(User $user): int;

    /**
     * Find a single notification belonging to the specified user by UUID.
     */
    public function findByIdForUser(User $user, string $id): ?DatabaseNotification;

    /**
     * Mark a specific notification belonging to the user as read.
     */
    public function markAsRead(User $user, string $id): ?DatabaseNotification;

    /**
     * Mark all notifications belonging to the user as read.
     *
     * @return int Number of updated notification rows.
     */
    public function markAllAsRead(User $user): int;

    /**
     * Delete/dismiss a notification belonging to the user.
     */
    public function deleteForUser(User $user, string $id): bool;
}
