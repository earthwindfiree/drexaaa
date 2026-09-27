<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AuditLogService
{
    public function record(
        User $actor,
        string $action,
        ?User $user = null,
        ?Model $entity = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
    ): AuditLog {
        Gate::forUser($actor)->authorize('admin');
        $action = trim($action);

        if ($action === '' || mb_strlen($action) > 120) {
            throw ValidationException::withMessages([
                'action' => ['Audit action must be between 1 and 120 characters.'],
            ]);
        }

        $auditLog = new AuditLog;
        $auditLog->actor()->associate($actor);
        $auditLog->user()->associate($user);

        if ($entity !== null) {
            $auditLog->entity()->associate($entity);
        }

        $auditLog->action = $action;
        $auditLog->old_values = $oldValues;
        $auditLog->new_values = $newValues;
        $auditLog->metadata = $metadata;
        $auditLog->save();

        return $auditLog;
    }
}
