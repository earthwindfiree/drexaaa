<?php

namespace App\Services;

use App\Models\Strategy;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AdminStrategyService
{
    public function update(Strategy $strategy, User $actor, array $changes): Strategy
    {
        Gate::forUser($actor)->authorize('super-admin');

        foreach (['name', 'description', 'risk_profile'] as $field) {
            if (array_key_exists($field, $changes)) {
                $changes[$field] = trim($changes[$field]);

                if ($changes[$field] === '') {
                    throw ValidationException::withMessages([
                        $field => ['This field cannot be blank.'],
                    ]);
                }
            }
        }

        return DB::transaction(function () use ($strategy, $actor, $changes): Strategy {
            $strategy = Strategy::query()->lockForUpdate()->findOrFail($strategy->getKey());
            $oldValues = $this->strategyValues($strategy);

            if (isset($changes['name']) && Strategy::query()
                ->where('name', $changes['name'])
                ->whereKeyNot($strategy->getKey())
                ->exists()) {
                throw ValidationException::withMessages([
                    'name' => ['Strategy names must be unique.'],
                ]);
            }

            foreach ($changes as $field => $value) {
                $strategy->{$field} = $value;
            }

            if ($strategy->getDirty() === []) {
                return $strategy;
            }

            $strategy->save();
            $newValues = $this->strategyValues($strategy);

            app(AuditLogService::class)->record(
                $actor,
                'strategy.configuration.updated',
                entity: $strategy,
                oldValues: $oldValues,
                newValues: $newValues,
                metadata: ['changed_fields' => array_keys($changes)],
            );

            return $strategy;
        }, 3);
    }

    private function strategyValues(Strategy $strategy): array
    {
        return [
            'name' => $strategy->name,
            'description' => $strategy->description,
            'risk_profile' => $strategy->risk_profile,
            'active' => $strategy->active,
        ];
    }
}
