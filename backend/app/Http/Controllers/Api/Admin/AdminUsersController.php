<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminUserListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUsersController extends Controller
{
    public function index(Request $request, AdminUserListService $userListService): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:active,suspended'],
            'role' => ['nullable', 'string', 'in:user,admin,super_admin'],
            'tier' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $users = $userListService->paginate($filters);

        return response()->json([
            'data' => $users->getCollection()->map(fn ($user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'country' => $user->country,
                'phone' => $user->phone,
                'role' => $user->role,
                'status' => $user->status,
                'account' => $user->account ? [
                    'id' => $user->account->id,
                    'managed_balance' => $user->account->managed_balance,
                    'pending_balance' => $user->account->pending_balance,
                    'tier' => $user->account->tier ? [
                        'id' => $user->account->tier->id,
                        'name' => $user->account->tier->name,
                    ] : null,
                    'trading_status' => $user->account->trading_status,
                ] : null,
                'created_at' => $user->created_at?->toISOString(),
            ])->values(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
                'total' => $users->total(),
            ],
        ]);
    }
}
