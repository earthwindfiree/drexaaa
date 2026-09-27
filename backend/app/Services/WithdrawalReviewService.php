<?php

namespace App\Services;

use App\Enums\WithdrawalStatus;
use App\Models\Account;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\FixedDecimalMath;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class WithdrawalReviewService
{
    public function __construct(
        private readonly AccountBalanceService $accountBalanceService,
        private readonly TransactionRecordService $transactionRecordService,
    ) {}

    public function approve(Withdrawal $withdrawal, User $reviewer): Withdrawal
    {
        $this->assertReviewer($reviewer);

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

            return $withdrawal->refresh();
        }, 3);
    }

    public function reject(Withdrawal $withdrawal, User $reviewer, string $reason): Withdrawal
    {
        $this->assertReviewer($reviewer);
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

            $withdrawal->status = WithdrawalStatus::Rejected;
            $withdrawal->rejection_reason = $reason;
            $withdrawal->reviewed_by = $reviewer->getKey();
            $withdrawal->reviewed_at = now();
            $withdrawal->save();

            return $withdrawal->refresh();
        }, 3);
    }

    private function assertReviewer(User $reviewer): void
    {
        if (! in_array($reviewer->role, ['admin', 'super_admin'], true) || $reviewer->status !== 'active') {
            throw new AuthorizationException('Only an active administrator may review withdrawals.');
        }
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
