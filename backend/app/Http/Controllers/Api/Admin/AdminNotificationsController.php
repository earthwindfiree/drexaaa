<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use App\Services\UserNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificationsController extends Controller
{
    public function index(Request $request, UserNotificationService $notifications): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:all,read,unread'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $items = $notifications->adminHistory($filters);

        return response()->json([
            'data' => $items->getCollection()->map(fn (UserNotification $notification): array => [
                'id' => $notification->id,
                'user' => $notification->user ? [
                    'id' => $notification->user->id,
                    'name' => $notification->user->name,
                    'email' => $notification->user->email,
                ] : null,
                'category' => $notification->category,
                'title' => $notification->title,
                'message' => $notification->message,
                'read_at' => $notification->read_at?->toISOString(),
                'created_at' => $notification->created_at?->toISOString(),
            ])->values(),
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
}
