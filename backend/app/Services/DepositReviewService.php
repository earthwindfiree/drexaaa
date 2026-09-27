<?php

namespace App\Services;

use App\Enums\DepositStatus;
use App\Models\Account;
use App\Models\Deposit;
use App\Models\User;
use App\Support\FixedDecimalMath;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;

class DepositReviewService
{
    public function __construct(
        private readonly AccountBalanceService $accountBalanceService,
        private readonly TransactionRecordService $transactionRecordService,
    ) {}

    public function confirm(Deposit $deposit, User $reviewer): Deposit
    {
        $this->assertReviewer($reviewer);

        return DB::transaction(function () use ($deposit, $reviewer): Deposit {
            $deposit = Deposit::query()->lockForUpdate()->findOrFail($deposit->getKey());
            $this->assertPending($deposit);
            $account = Account::query()->lockForUpdate()->find($deposit->account_id);

            if (! $account) {
                throw new ModelNotFoundException('The deposit account no longer exists.');
            }

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

            return $deposit->refresh();
        }, 3);
    }

    public function reject(Deposit $deposit, User $reviewer, string $reason): Deposit
    {
        $this->assertReviewer($reviewer);
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

            return $deposit->refresh();
        }, 3);
    }

    private function assertReviewer(User $reviewer): void
    {
        if (! in_array($reviewer->role, ['admin', 'super_admin'], true) || $reviewer->status !== 'active') {
            throw new AuthorizationException('Only an active administrator may review deposits.');
        }
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
