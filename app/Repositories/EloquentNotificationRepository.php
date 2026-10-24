<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;

final class EloquentNotificationRepository implements NotificationRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function forUser(User $user, ?bool $unreadOnly = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = $user->notifications()->latest();

        if ($unreadOnly === true) {
            $query->whereNull('read_at');
        } elseif ($unreadOnly === false) {
            $query->whereNotNull('read_at');
        }

        return $query->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function unreadCountForUser(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /**
     * {@inheritdoc}
     */
    public function findByIdForUser(User $user, string $id): ?DatabaseNotification
    {
        /** @var DatabaseNotification|null $notification */
        $notification = $user->notifications()->where('id', $id)->first();

        return $notification;
    }

    /**
     * {@inheritdoc}
     */
    public function markAsRead(User $user, string $id): ?DatabaseNotification
    {
        $notification = $this->findByIdForUser($user, $id);

        if ($notification === null) {
            return null;
        }

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return $notification->fresh();
    }

    /**
     * {@inheritdoc}
     */
    public function markAllAsRead(User $user): int
    {
        $unread = $user->unreadNotifications();
        $count = $unread->count();

        if ($count > 0) {
            $user->unreadNotifications()->update(['read_at' => now()]);
        }

        return $count;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteForUser(User $user, string $id): bool
    {
        $notification = $this->findByIdForUser($user, $id);

        if ($notification === null) {
            return false;
        }

        return (bool) $notification->delete();
    }
}
