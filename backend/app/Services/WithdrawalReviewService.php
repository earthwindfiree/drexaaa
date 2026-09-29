<?php

namespace App\Services;

use App\Enums\WithdrawalStatus;
use App\Models\Account;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\FixedDecimalMath;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class WithdrawalReviewService
{
    public function __construct(
        private readonly AccountBalanceService $accountBalanceService,
        private readonly TransactionRecordService $transactionRecordService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function approve(Withdrawal $withdrawal, User $reviewer): Withdrawal
    {
        Gate::forUser($reviewer)->authorize('admin');

        return DB::transaction(function () use ($withdrawal, $reviewer): Withdrawal {
            $snapshot = Withdrawal::query()->findOrFail($withdrawal->getKey());
            $account = Account::query()->lockForUpdate()->find($snapshot->account_id);

            if (! $account) {
                throw new ModelNotFoundException('The withdrawal account no longer exists.');
            }

            $withdrawal = Withdrawal::query()
                ->where('account_id', $account->getKey())
                ->lockForUpdate()
                ->findOrFail($snapshot->getKey());
            $this->assertPending($withdrawal);
            $oldValues = [
                'status' => $withdrawal->status->value,
                'amount' => $withdrawal->amount,
                'managed_balance' => $account->managed_balance,
                'tier_id' => $account->tier_id,
            ];

            try {
                $managedBalance = FixedDecimalMath::subtractNonNegative(
                    FixedDecimalMath::normalize($account->managed_balance, 18, 2, 'managed_balance'),
                    FixedDecimalMath::normalize($withdrawal->amount, 18, 2, 'amount'),
                    18,
                    2,
                );
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages([
                    'withdrawal' => ['Insufficient managed balance to approve this withdrawal.'],
                ]);
            }

            $this->accountBalanceService->setManagedBalance($account, $managedBalance);
            $withdrawal->status = WithdrawalStatus::Approved;
            $withdrawal->reviewed_by = $reviewer->getKey();
            $withdrawal->reviewed_at = now();
            $withdrawal->rejection_reason = null;
            $withdrawal->save();
            $this->transactionRecordService->recordApprovedWithdrawal($withdrawal);
            $this->auditLogService->record(
                $reviewer,
                'withdrawal.approved',
                $withdrawal->user,
                $withdrawal,
                $oldValues,
                [
                    'status' => $withdrawal->status->value,
                    'amount' => $withdrawal->amount,
                    'managed_balance' => $account->managed_balance,
                    'tier_id' => $account->tier_id,
                ],
            );
            app(UserNotificationService::class)->create(
                $withdrawal->user,
                'financial',
                'Withdrawal approved',
                'Withdrawal #'.$withdrawal->id.' was approved and deducted from your managed balance.',
            );

            if ($oldValues['tier_id'] !== $account->tier_id) {
                app(UserNotificationService::class)->create(
                    $withdrawal->user,
                    'account',
                    'Tier changed',
                    'Your account tier changed after the managed balance update.',
                );
            }

            return $withdrawal->refresh();
        }, 3);
    }

    public function reject(Withdrawal $withdrawal, User $reviewer, string $reason): Withdrawal
    {
        Gate::forUser($reviewer)->authorize('admin');
        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages([
                'rejection_reason' => ['A rejection reason of 1 to 1000 characters is required.'],
            ]);
        }

        return DB::transaction(function () use ($withdrawal, $reviewer, $reason): Withdrawal {
            $snapshot = Withdrawal::query()->findOrFail($withdrawal->getKey());
            $account = Account::query()->lockForUpdate()->find($snapshot->account_id);

            if (! $account) {
                throw new ModelNotFoundException('The withdrawal account no longer exists.');
            }

            $withdrawal = Withdrawal::query()
                ->where('account_id', $account->getKey())
                ->lockForUpdate()
                ->findOrFail($snapshot->getKey());
            $this->assertPending($withdrawal);
            $oldValues = [
                'status' => $withdrawal->status->value,
                'amount' => $withdrawal->amount,
                'managed_balance' => $account->managed_balance,
                'tier_id' => $account->tier_id,
            ];

            $withdrawal->status = WithdrawalStatus::Rejected;
            $withdrawal->rejection_reason = $reason;
            $withdrawal->reviewed_by = $reviewer->getKey();
            $withdrawal->reviewed_at = now();
            $withdrawal->save();
            $this->auditLogService->record(
                $reviewer,
                'withdrawal.rejected',
                $withdrawal->user,
                $withdrawal,
                $oldValues,
                [
                    'status' => $withdrawal->status->value,
                    'amount' => $withdrawal->amount,
                    'rejection_reason' => $withdrawal->rejection_reason,
                    'managed_balance' => $account->managed_balance,
                    'tier_id' => $account->tier_id,
                ],
            );
            app(UserNotificationService::class)->create(
                $withdrawal->user,
                'financial',
                'Withdrawal rejected',
                'Withdrawal #'.$withdrawal->id.' was rejected. The reserved amount is available again.',
            );

            return $withdrawal->refresh();
        }, 3);
    }

    private function assertPending(Withdrawal $withdrawal): void
    {
        if ($withdrawal->status !== WithdrawalStatus::Pending) {
            throw ValidationException::withMessages([
                'withdrawal' => ['Only pending withdrawals may be reviewed.'],
            ]);
        }
    }
}
