<?php

namespace App\Services;

use App\Enums\WithdrawalStatus;
use App\Models\Account;
use App\Support\FixedDecimalMath;
use InvalidArgumentException;
use LogicException;

class WithdrawalAvailabilityService
{
    public function withdrawableAmount(Account $account, bool $lockPending = false): string
    {
        $pendingWithdrawals = $account->withdrawals()
            ->where('status', WithdrawalStatus::Pending->value)
            ->select(['id', 'amount']);

        if ($lockPending) {
            $pendingWithdrawals->lockForUpdate();
        }

        $reservedAmount = '0.00';

        foreach ($pendingWithdrawals->get() as $withdrawal) {
            $reservedAmount = FixedDecimalMath::add(
                $reservedAmount,
                $withdrawal->amount,
                18,
                2,
            );
        }

        try {
            return FixedDecimalMath::subtractNonNegative(
                FixedDecimalMath::normalize($account->managed_balance, 18, 2, 'managed_balance'),
                $reservedAmount,
                18,
                2,
            );
        } catch (InvalidArgumentException $exception) {
            throw new LogicException('Pending withdrawal reservations exceed the managed balance.', 0, $exception);
        }
    }
}
