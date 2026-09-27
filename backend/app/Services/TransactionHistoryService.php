<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TransactionHistoryService
{
    public function forUser(User $user, ?TransactionType $type = null, ?int $accountId = null): Builder
    {
        return Transaction::query()
            ->where('user_id', $user->getKey())
            ->when($accountId, fn (Builder $query) => $query->where('account_id', $accountId))
            ->when($type, fn (Builder $query) => $query->where('type', $type->value))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');
    }

    public function forAccount(Account $account, ?TransactionType $type = null): Builder
    {
        return Transaction::query()
            ->where('account_id', $account->getKey())
            ->when($type, fn (Builder $query) => $query->where('type', $type->value))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');
    }
}
