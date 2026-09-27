<?php

namespace App\Services;

use App\Enums\DepositStatus;
use App\Enums\WithdrawalStatus;
use App\Models\Account;
use App\Models\Asset;
use App\Models\Deposit;
use App\Models\Tier;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\FixedDecimalMath;

class AdminDashboardSummaryService
{
    public function summarize(): array
    {
        return [
            'total_users' => User::query()->count(),
            'active_users' => User::query()->where('status', 'active')->count(),
            'suspended_users' => User::query()->where('status', 'suspended')->count(),
            'total_managed_balance' => $this->moneySum('managed_balance'),
            'total_pending_balance' => $this->moneySum('pending_balance'),
            'total_pending_deposits' => Deposit::query()->where('status', DepositStatus::Pending->value)->count(),
            'total_pending_withdrawals' => Withdrawal::query()->where('status', WithdrawalStatus::Pending->value)->count(),
            'total_completed_deposits' => Deposit::query()->where('status', DepositStatus::Confirmed->value)->count(),
            'total_approved_withdrawals' => Withdrawal::query()->where('status', WithdrawalStatus::Approved->value)->count(),
            'total_assets' => Asset::query()->count(),
            'active_assets' => Asset::query()->where('active', true)->count(),
            'total_tiers' => Tier::query()->count(),
            'total_accounts' => Account::query()->count(),
        ];
    }

    private function moneySum(string $column): string
    {
        return FixedDecimalMath::normalize(
            (string) (Account::query()->sum($column) ?: '0'),
            18,
            2,
            $column,
        );
    }
}
