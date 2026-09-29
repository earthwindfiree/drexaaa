<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Strategy;
use App\Models\Tier;
use App\Models\User;
use App\Support\FixedDecimalMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class AdminTierService
{
    public function list(): array
    {
        return [
            'tiers' => Tier::query()->with('strategy')->orderBy('minimum_balance')->orderBy('id')->get(),
            'strategies' => Strategy::query()->orderBy('name')->get(),
        ];
    }

    public function update(Tier $tier, User $actor, array $changes): Tier
    {
        Gate::forUser($actor)->authorize('super-admin');

        try {
            $changes = $this->normalizeChanges($changes);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['tier' => [$exception->getMessage()]]);
        }

        return DB::transaction(function () use ($tier, $actor, $changes): Tier {
            $tier = Tier::query()->lockForUpdate()->findOrFail($tier->getKey());
            $oldValues = $this->tierValues($tier);
            $candidateValues = array_merge($oldValues, $changes);

            $this->assertUniqueTierName($candidateValues['name'], $tier->getKey());
            $this->assertUniqueThreshold($candidateValues['minimum_balance'], $tier->getKey());

            foreach ($changes as $field => $value) {
                $tier->{$field} = $value;
            }

            if ($tier->getDirty() === []) {
                return $tier->fresh('strategy');
            }

            $tier->save();
            $recalculatedAccounts = $this->recalculateAccounts();
            $newValues = $this->tierValues($tier);

            app(AuditLogService::class)->record(
                $actor,
                'tier.configuration.updated',
                entity: $tier,
                oldValues: $oldValues,
                newValues: $newValues,
                metadata: [
                    'changed_fields' => array_keys($changes),
                    'recalculated_accounts' => $recalculatedAccounts,
                ],
            );

            return $tier->fresh('strategy');
        }, 3);
    }

    private function normalizeChanges(array $changes): array
    {
        if (array_key_exists('minimum_balance', $changes)) {
            $changes['minimum_balance'] = FixedDecimalMath::normalize((string) $changes['minimum_balance'], 18, 2, 'minimum_balance');
        }

        foreach (['name', 'description'] as $field) {
            if (array_key_exists($field, $changes)) {
                $changes[$field] = trim((string) $changes[$field]);
            }
        }

        return $changes;
    }

    private function tierValues(Tier $tier): array
    {
        return [
            'name' => $tier->name,
            'minimum_balance' => $tier->minimum_balance,
            'strategy_id' => $tier->strategy_id,
            'description' => $tier->description,
            'benefits' => $tier->benefits,
            'feature_access' => $tier->feature_access,
            'display_settings' => $tier->display_settings,
        ];
    }

    private function assertUniqueTierName(string $name, int $tierId): void
    {
        if (Tier::query()->where('name', $name)->whereKeyNot($tierId)->exists()) {
            throw ValidationException::withMessages(['name' => ['Tier names must be unique.']]);
        }
    }

    private function assertUniqueThreshold(string $minimumBalance, int $tierId): void
    {
        if (Tier::query()->where('minimum_balance', $minimumBalance)->whereKeyNot($tierId)->exists()) {
            throw ValidationException::withMessages(['minimum_balance' => ['Tier thresholds must be unique.']]);
        }
    }

    private function recalculateAccounts(): int
    {
        $calculator = app(TierCalculator::class);
        $notifications = app(UserNotificationService::class);
        $updated = 0;

        Account::query()->lockForUpdate()->get(['id', 'user_id', 'managed_balance', 'tier_id'])->each(function (Account $account) use ($calculator, $notifications, &$updated): void {
            $tier = $calculator->qualifyingTier($account->managed_balance);
            $tierId = $tier?->id;

            if ($tierId !== $account->tier_id) {
                $account->tier_id = $tierId;
                $account->save();
                $notifications->create(
                    $account->user,
                    'account',
                    'Tier changed',
                    $tier
                        ? 'Your account tier changed to '.$tier->name.' after tier configuration was updated.'
                        : 'Your account no longer qualifies for a configured tier after tier configuration was updated.',
                );
                $updated++;
            }
        });

        return $updated;
    }
}
