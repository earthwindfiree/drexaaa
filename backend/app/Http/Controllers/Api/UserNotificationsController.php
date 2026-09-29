<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use App\Services\UserNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserNotificationsController extends Controller
{
    public function index(Request $request, UserNotificationService $notifications): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:all,read,unread'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $user = $request->user();
        $query = $notifications->history($user)
            ->when(($filters['status'] ?? 'all') === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->when(($filters['status'] ?? 'all') === 'unread', fn ($query) => $query->whereNull('read_at'));
        $items = $query->paginate($filters['per_page'] ?? 15, ['*'], 'page', $filters['page'] ?? 1);

        return response()->json([
            'data' => $items->getCollection()->map(fn (UserNotification $notification): array => $this->notificationData($notification))->values(),
            'unread_count' => $user->notifications()->whereNull('read_at')->count(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function markRead(Request $request, int $notification, UserNotificationService $notifications): JsonResponse
    {
        $updated = $notifications->markRead($request->user(), $notification);

        return response()->json(['data' => $this->notificationData($updated)]);
    }

    public function markAllRead(Request $request, UserNotificationService $notifications): JsonResponse
    {
        return response()->json(['data' => ['updated' => $notifications->markAllRead($request->user())]]);
    }

    private function notificationData(UserNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'category' => $notification->category,
            'title' => $notification->title,
            'message' => $notification->message,
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
        ];
    }
}
