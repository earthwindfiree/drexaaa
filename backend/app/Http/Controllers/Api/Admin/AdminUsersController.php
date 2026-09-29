<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminUserListService;
use App\Services\AdminUserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

    public function updateAccess(Request $request, User $user, AdminUserManagementService $userManagementService): JsonResponse
    {
        $allowedFields = ['role', 'status'];

        if (array_diff(array_keys($request->all()), $allowedFields) !== []) {
            throw ValidationException::withMessages(['user' => ['Only role and admin account status may be changed.']]);
        }

        $changes = $request->validate([
            'role' => ['sometimes', 'required', 'string', 'in:user,admin,super_admin'],
            'status' => ['sometimes', 'required', 'string', 'in:active,suspended'],
        ]);

        if ($changes === []) {
            throw ValidationException::withMessages(['user' => ['At least one access field is required.']]);
        }

        $updated = $userManagementService->updateAccess($user, $request->user(), $changes);

        return response()->json(['data' => [
            'id' => $updated->id,
            'role' => $updated->role,
            'status' => $updated->status,
        ]]);
    }
}
