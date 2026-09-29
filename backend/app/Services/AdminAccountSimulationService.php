<?php

namespace App\Services;

use App\Models\Account;
use App\Models\User;
use App\Support\FixedDecimalMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class AdminAccountSimulationService
{
    public function __construct(
        private readonly AccountBalanceService $accountBalanceService,
        private readonly TransactionRecordService $transactionRecordService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function update(Account $account, User $actor, array $changes): Account
    {
        Gate::forUser($actor)->authorize('super-admin');

        try {
            $normalizedChanges = $this->normalizeChanges($changes);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'account' => [$exception->getMessage()],
            ]);
        }

        return DB::transaction(function () use ($account, $actor, $normalizedChanges): Account {
            $account = Account::query()->lockForUpdate()->findOrFail($account->getKey());
            $oldValues = [
                'managed_balance' => $account->managed_balance,
                'pending_balance' => $account->pending_balance,
                'total_profit_loss' => $account->total_profit_loss,
                'performance_percentage' => $account->performance_percentage,
                'trading_status' => $account->trading_status,
                'tier_id' => $account->tier_id,
            ];
            $changedFields = [];

            if (array_key_exists('managed_balance', $normalizedChanges) && $normalizedChanges['managed_balance'] !== $account->managed_balance) {
                $this->accountBalanceService->setManagedBalance($account, $normalizedChanges['managed_balance']);
                $changedFields[] = 'managed_balance';
            }

            foreach (['total_profit_loss', 'performance_percentage', 'trading_status'] as $field) {
                if (array_key_exists($field, $normalizedChanges) && $normalizedChanges[$field] !== $account->{$field}) {
                    $account->{$field} = $normalizedChanges[$field];
                    $changedFields[] = $field;
                }
            }

            if ($changedFields === []) {
                return $account->refresh();
            }

            $account->save();
            $newValues = [
                'managed_balance' => $account->managed_balance,
                'pending_balance' => $account->pending_balance,
                'total_profit_loss' => $account->total_profit_loss,
                'performance_percentage' => $account->performance_percentage,
                'trading_status' => $account->trading_status,
                'tier_id' => $account->tier_id,
            ];
            $financialChange = array_intersect($changedFields, ['managed_balance', 'total_profit_loss', 'performance_percentage']) !== [];
            $transaction = null;

            if ($financialChange) {
                $adjustmentAmount = '0.00';

                if (in_array('managed_balance', $changedFields, true)) {
                    $adjustmentAmount = FixedDecimalMath::subtractSigned($newValues['managed_balance'], $oldValues['managed_balance'], 18, 2);
                } elseif (in_array('total_profit_loss', $changedFields, true)) {
                    $adjustmentAmount = FixedDecimalMath::subtractSigned($newValues['total_profit_loss'], $oldValues['total_profit_loss'], 18, 2);
                }

                $transaction = $this->transactionRecordService->recordAccountAdjustment(
                    $account,
                    $adjustmentAmount,
                    'Administrative simulation adjustment: '.implode(', ', $changedFields),
                );
                app(UserNotificationService::class)->create(
                    $account->user,
                    'financial',
                    'Account adjusted',
                    'An administrator made a simulated financial account adjustment.',
                );
            }

            $this->auditLogService->record(
                $actor,
                'account.simulation.updated',
                $account->user,
                $account,
                $oldValues,
                $newValues,
                [
                    'changed_fields' => $changedFields,
                    'transaction_id' => $transaction?->id,
                ],
            );

            if ($oldValues['tier_id'] !== $newValues['tier_id']) {
                app(UserNotificationService::class)->create(
                    $account->user,
                    'account',
                    'Tier changed',
                    'Your account tier changed after the managed balance update.',
                );
            }

            return $account->refresh();
        }, 3);
    }

    private function normalizeChanges(array $changes): array
    {
        $normalized = [];

        if (array_key_exists('managed_balance', $changes)) {
            $normalized['managed_balance'] = FixedDecimalMath::normalize((string) $changes['managed_balance'], 18, 2, 'managed_balance');
        }

        if (array_key_exists('total_profit_loss', $changes)) {
            $normalized['total_profit_loss'] = FixedDecimalMath::normalizeSigned((string) $changes['total_profit_loss'], 18, 2, 'total_profit_loss');
        }

        if (array_key_exists('performance_percentage', $changes)) {
            $normalized['performance_percentage'] = FixedDecimalMath::normalizeSigned((string) $changes['performance_percentage'], 5, 4, 'performance_percentage');
        }

        if (array_key_exists('trading_status', $changes)) {
            $normalized['trading_status'] = (string) $changes['trading_status'];
        }

        return $normalized;
    }
}
