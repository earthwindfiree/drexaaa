<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminAuditLogService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = AuditLog::query()
            ->select(['id', 'actor_id', 'user_id', 'action', 'entity_type', 'entity_id', 'old_values', 'new_values', 'metadata', 'created_at', 'updated_at'])
            ->with([
                'actor:id,name,email',
                'user:id,name,email',
            ])
            ->when($filters['actor_search'] ?? null, function ($query, string $search): void {
                $query->whereHas('actor', function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['target_search'] ?? null, function ($query, string $search): void {
                $query->whereHas('user', function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['action'] ?? null, fn ($query, string $action) => $query->where('action', 'like', "%{$action}%"))
            ->when($filters['entity_type'] ?? null, fn ($query, string $entityType) => $query->where('entity_type', $entityType))
            ->when($filters['entity_id'] ?? null, fn ($query, int $entityId) => $query->where('entity_id', $entityId))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return $query->paginate($filters['per_page'] ?? 15, ['*'], 'page', $filters['page'] ?? 1);
    }
}
