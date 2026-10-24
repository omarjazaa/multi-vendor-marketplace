<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Http\Traits\ApiResponse;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    /**
     * List paginated notifications for the authenticated user.
     *
     * Supports ?unread=1 or ?unread=0 filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $unreadFilter = null;
        if ($request->has('unread')) {
            $unreadFilter = filter_var($request->query('unread'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        $perPage = (int) $request->query('per_page', 15);
        if ($perPage < 1 || $perPage > 50) {
            $perPage = 15;
        }

        $paginator = $this->notificationService->listUserNotifications(
            $request->user(),
            $unreadFilter,
            $perPage
        );

        return $this->successResponse([
            'notifications' => NotificationResource::collection($paginator)->response()->getData(true),
        ]);
    }

    /**
     * Get the count of unread notifications for the authenticated user.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $this->notificationService->getUnreadCount($request->user());

        return $this->successResponse([
            'unread_count' => $count,
        ]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $this->notificationService->markAsRead($request->user(), $id);

        if ($notification === null) {
            return $this->errorResponse('Notification not found.', [
                'notification' => ['Notification not found.'],
            ], 404);
        }

        return $this->successResponse([
            'notification' => new NotificationResource($notification),
        ], 'Notification marked as read.');
    }

    /**
     * Mark all notifications belonging to the authenticated user as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $markedCount = $this->notificationService->markAllAsRead($request->user());

        return $this->successResponse([
            'marked_count' => $markedCount,
        ], 'All notifications marked as read.');
    }

    /**
     * Delete / dismiss a notification.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $deleted = $this->notificationService->deleteNotification($request->user(), $id);

        if (! $deleted) {
            return $this->errorResponse('Notification not found.', [
                'notification' => ['Notification not found.'],
            ], 404);
        }

        return $this->successResponse([], 'Notification dismissed successfully.');
    }
}
