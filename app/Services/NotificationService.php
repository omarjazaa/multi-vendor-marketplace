<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;

final class NotificationService
{
    /**
     * NotificationService coordinates notification retrieval, unread tracking,
     * and status mutations adhering to the Service Layer pattern.
     */
    public function __construct(
        private readonly NotificationRepositoryInterface $notifications
    ) {}

    /**
     * Get paginated notifications for the authenticated user.
     */
    public function listUserNotifications(User $user, ?bool $unreadOnly = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->notifications->forUser($user, $unreadOnly, $perPage);
    }

    /**
     * Get unread notification count for the authenticated user.
     */
    public function getUnreadCount(User $user): int
    {
        return $this->notifications->unreadCountForUser($user);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(User $user, string $id): ?DatabaseNotification
    {
        return $this->notifications->markAsRead($user, $id);
    }

    /**
     * Mark all notifications for the user as read.
     */
    public function markAllAsRead(User $user): int
    {
        return $this->notifications->markAllAsRead($user);
    }

    /**
     * Delete / dismiss a notification for the user.
     */
    public function deleteNotification(User $user, string $id): bool
    {
        return $this->notifications->deleteForUser($user, $id);
    }
}
