<?php

namespace App\Services;

use App\Enums\DepositStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\WithdrawalStatus;
use App\Models\Account;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Models\Withdrawal;
use Illuminate\Support\Carbon;
use LogicException;

class TransactionRecordService
{
    public function recordAccountAdjustment(Account $account, string $usdAmount, string $description): Transaction
    {
        $transaction = new Transaction;
        $transaction->user()->associate($account->user);
        $transaction->account()->associate($account);
        $transaction->type = TransactionType::Adjustment;
        $transaction->status = TransactionStatus::Completed;
        $transaction->usd_amount = $usdAmount;
        $transaction->reference = 'ADJ-'.$account->getKey().'-'.str()->uuid();
        $transaction->description = $description;
        $transaction->occurred_at = Carbon::now();
        $transaction->save();

        return $transaction;
    }

    public function recordConfirmedDeposit(Deposit $deposit): Transaction
    {
        if ($deposit->status !== DepositStatus::Confirmed) {
            throw new LogicException('Only confirmed deposits may create completed transactions.');
        }

        $existing = Transaction::query()->where('deposit_id', $deposit->getKey())->first();

        if ($existing) {
            return $existing;
        }

        $transaction = new Transaction;
        $transaction->user()->associate($deposit->user);
        $transaction->account()->associate($deposit->account);
        $transaction->asset()->associate($deposit->asset);
        $transaction->deposit()->associate($deposit);
        $transaction->type = TransactionType::Deposit;
        $transaction->status = TransactionStatus::Completed;
        $transaction->crypto_amount = $deposit->crypto_amount;
        $transaction->usd_amount = $deposit->usd_value;
        $transaction->price_snapshot = $deposit->price_snapshot;
        $transaction->reference = $deposit->transaction_reference;
        $transaction->occurred_at = $deposit->reviewed_at ?? Carbon::now();
        $transaction->save();

        return $transaction;
    }

    public function recordApprovedWithdrawal(Withdrawal $withdrawal): Transaction
    {
        if ($withdrawal->status !== WithdrawalStatus::Approved) {
            throw new LogicException('Only approved withdrawals may create completed transactions.');
        }

        $existing = Transaction::query()->where('withdrawal_id', $withdrawal->getKey())->first();

        if ($existing) {
            return $existing;
        }

        $transaction = new Transaction;
        $transaction->user()->associate($withdrawal->user);
        $transaction->account()->associate($withdrawal->account);
        $transaction->asset()->associate($withdrawal->asset);
        $transaction->withdrawal()->associate($withdrawal);
        $transaction->type = TransactionType::Withdrawal;
        $transaction->status = TransactionStatus::Completed;
        $transaction->crypto_amount = $withdrawal->crypto_amount;
        $transaction->usd_amount = $withdrawal->amount;
        $transaction->price_snapshot = $withdrawal->price_snapshot;
        $transaction->reference = 'WD-'.$withdrawal->getKey();
        $transaction->occurred_at = $withdrawal->reviewed_at ?? Carbon::now();
        $transaction->save();

        return $transaction;
    }
}
