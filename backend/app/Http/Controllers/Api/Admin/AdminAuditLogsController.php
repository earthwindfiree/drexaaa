<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AdminAuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuditLogsController extends Controller
{
    public function index(Request $request, AdminAuditLogService $auditLogService): JsonResponse
    {
        $filters = $request->validate([
            'actor_search' => ['nullable', 'string', 'max:100'],
            'target_search' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:120'],
            'entity_type' => ['nullable', 'string', 'max:255'],
            'entity_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $logs = $auditLogService->paginate($filters);

        return response()->json([
            'data' => $logs->getCollection()->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'actor' => $log->actor ? [
                    'id' => $log->actor->id,
                    'name' => $log->actor->name,
                    'email' => $log->actor->email,
                ] : null,
                'target_user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
                'action' => $log->action,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'metadata' => $log->metadata,
                'created_at' => $log->created_at?->toISOString(),
                'updated_at' => $log->updated_at?->toISOString(),
            ])->values(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
