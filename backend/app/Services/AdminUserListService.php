<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminUserListService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = User::query()
            ->select(['id', 'name', 'email', 'country', 'phone', 'role', 'status', 'created_at'])
            ->with([
                'account:id,user_id,managed_balance,pending_balance,tier_id,trading_status',
                'account.tier:id,name',
            ])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
            ->when($filters['tier'] ?? null, function ($query, string $tier): void {
                $query->whereHas('account.tier', fn ($query) => $query->where('name', 'like', "%{$tier}%"));
            })
            ->latest('created_at')
            ->latest('id');

        return $query->paginate($filters['per_page'] ?? 15, ['*'], 'page', $filters['page'] ?? 1);
    }
}
