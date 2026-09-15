<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Gate;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->notifications();

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $notifications = $query->paginate($request->integer('per_page', 15));

        return $this->success(
            NotificationResource::collection($notifications),
            meta: $this->paginationMeta($notifications)
        );
    }

    /**
     * How many unread notifications the authenticated user has —
     * powers the header bell's badge.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return $this->success(['count' => $request->user()->unreadNotifications()->count()]);
    }

    /**
     * Mark one notification as read.
     */
    public function markRead(Request $request, DatabaseNotification $notification): JsonResponse
    {
        Gate::authorize('markRead', $notification);

        if (! $notification->read_at) {
            $notification->markAsRead();
        }

        return $this->success(new NotificationResource($notification));
    }

    /**
     * Mark every notification for the authenticated user as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return $this->success(message: 'Barcha bildirishnomalar o\'qilgan deb belgilandi.');
    }
}
