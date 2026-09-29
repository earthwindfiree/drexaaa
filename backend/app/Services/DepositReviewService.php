<?php

namespace App\Services;

use App\Enums\DepositStatus;
use App\Models\Account;
use App\Models\Deposit;
use App\Models\User;
use App\Support\FixedDecimalMath;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;

class DepositReviewService
{
    public function __construct(
        private readonly AccountBalanceService $accountBalanceService,
        private readonly TransactionRecordService $transactionRecordService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function confirm(Deposit $deposit, User $reviewer): Deposit
    {
        Gate::forUser($reviewer)->authorize('admin');

        return DB::transaction(function () use ($deposit, $reviewer): Deposit {
            $deposit = Deposit::query()->lockForUpdate()->findOrFail($deposit->getKey());
            $this->assertPending($deposit);
            $account = Account::query()->lockForUpdate()->find($deposit->account_id);

            if (! $account) {
                throw new ModelNotFoundException('The deposit account no longer exists.');
            }

            $oldValues = [
                'status' => $deposit->status->value,
                'managed_balance' => $account->managed_balance,
                'pending_balance' => $account->pending_balance,
                'tier_id' => $account->tier_id,
            ];

            $pendingBalance = FixedDecimalMath::normalize($account->pending_balance, 18, 2, 'pending_balance');
            $usdValue = FixedDecimalMath::normalize($deposit->usd_value, 18, 2, 'usd_value');

            try {
                $account->pending_balance = FixedDecimalMath::subtractNonNegative($pendingBalance, $usdValue, 18, 2);
                $managedBalance = FixedDecimalMath::add(
                    FixedDecimalMath::normalize($account->managed_balance, 18, 2, 'managed_balance'),
                    $usdValue,
                    18,
                    2,
                );
            } catch (InvalidArgumentException $exception) {
                throw new LogicException('Account balances are inconsistent with the pending deposit.', 0, $exception);
            }

            $this->accountBalanceService->setManagedBalance($account, $managedBalance);

            $deposit->status = DepositStatus::Confirmed;
            $deposit->reviewed_by = $reviewer->getKey();
            $deposit->reviewed_at = now();
            $deposit->rejection_reason = null;
            $deposit->save();
            $this->transactionRecordService->recordConfirmedDeposit($deposit);
            $this->auditLogService->record(
                $reviewer,
                'deposit.confirmed',
                $deposit->user,
                $deposit,
                $oldValues,
                [
                    'status' => $deposit->status->value,
                    'managed_balance' => $account->managed_balance,
                    'pending_balance' => $account->pending_balance,
                    'tier_id' => $account->tier_id,
                ],
            );
            app(UserNotificationService::class)->create(
                $deposit->user,
                'financial',
                'Deposit approved',
                'Deposit #'.$deposit->id.' was approved and credited to your managed balance.',
            );

            if ($oldValues['tier_id'] !== $account->tier_id) {
                app(UserNotificationService::class)->create(
                    $deposit->user,
                    'account',
                    'Tier changed',
                    'Your account tier changed after the managed balance update.',
                );
            }

            return $deposit->refresh();
        }, 3);
    }

    public function reject(Deposit $deposit, User $reviewer, string $reason): Deposit
    {
        Gate::forUser($reviewer)->authorize('admin');
        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages([
                'rejection_reason' => ['A rejection reason of 1 to 1000 characters is required.'],
            ]);
        }

        return DB::transaction(function () use ($deposit, $reviewer, $reason): Deposit {
            $deposit = Deposit::query()->lockForUpdate()->findOrFail($deposit->getKey());
            $this->assertPending($deposit);
            $account = Account::query()->lockForUpdate()->find($deposit->account_id);

            if (! $account) {
                throw new ModelNotFoundException('The deposit account no longer exists.');
            }

            $oldValues = [
                'status' => $deposit->status->value,
                'managed_balance' => $account->managed_balance,
                'pending_balance' => $account->pending_balance,
                'tier_id' => $account->tier_id,
            ];

            try {
                $account->pending_balance = FixedDecimalMath::subtractNonNegative(
                    FixedDecimalMath::normalize($account->pending_balance, 18, 2, 'pending_balance'),
                    FixedDecimalMath::normalize($deposit->usd_value, 18, 2, 'usd_value'),
                    18,
                    2,
                );
            } catch (InvalidArgumentException $exception) {
                throw new LogicException('Account pending balance is inconsistent with the pending deposit.', 0, $exception);
            }

            $account->save();
            $deposit->status = DepositStatus::Rejected;
            $deposit->rejection_reason = $reason;
            $deposit->reviewed_by = $reviewer->getKey();
            $deposit->reviewed_at = now();
            $deposit->save();
            $this->auditLogService->record(
                $reviewer,
                'deposit.rejected',
                $deposit->user,
                $deposit,
                $oldValues,
                [
                    'status' => $deposit->status->value,
                    'rejection_reason' => $deposit->rejection_reason,
                    'managed_balance' => $account->managed_balance,
                    'pending_balance' => $account->pending_balance,
                    'tier_id' => $account->tier_id,
                ],
            );
            app(UserNotificationService::class)->create(
                $deposit->user,
                'financial',
                'Deposit rejected',
                'Deposit #'.$deposit->id.' was rejected. The pending balance has been released.',
            );

            return $deposit->refresh();
        }, 3);
    }

    private function assertPending(Deposit $deposit): void
    {
        if ($deposit->status !== DepositStatus::Pending) {
            throw ValidationException::withMessages([
                'deposit' => ['Only pending deposits may be reviewed.'],
            ]);
        }
    }
}
