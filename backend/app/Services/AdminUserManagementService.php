<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AdminUserManagementService
{
    public function updateAccess(User $target, User $actor, array $changes): User
    {
        Gate::forUser($actor)->authorize('super-admin');

        return DB::transaction(function () use ($target, $actor, $changes): User {
            $target = User::query()->lockForUpdate()->findOrFail($target->getKey());
            $newRole = $changes['role'] ?? $target->role;

            if (array_key_exists('status', $changes) && ! in_array($newRole, ['admin', 'super_admin'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Only admin accounts may be suspended or reactivated here.'],
                ]);
            }

            $oldValues = $target->only(['role', 'status']);

            foreach ($changes as $field => $value) {
                $target->{$field} = $value;
            }

            if ($target->getDirty() === []) {
                return $target;
            }

            $target->save();

            app(AuditLogService::class)->record(
                $actor,
                'user.access.updated',
                user: $target,
                entity: $target,
                oldValues: $oldValues,
                newValues: $target->only(['role', 'status']),
                metadata: ['changed_fields' => array_keys($changes)],
            );
            app(UserNotificationService::class)->create(
                $target,
                'security',
                'Account access updated',
                'An administrator changed your account role or status.',
            );

            return $target->refresh();
        }, 3);
    }
}
