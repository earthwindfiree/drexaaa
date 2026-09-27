<?php

namespace App\Services;

use App\Models\Account;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminAccountService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Account::query()
            ->select(['id', 'user_id', 'managed_balance', 'pending_balance', 'total_profit_loss', 'performance_percentage', 'trading_status', 'tier_id', 'created_at', 'updated_at'])
            ->with([
                'user:id,name,email',
                'tier:id,name',
            ])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->whereHas('user', function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['tier'] ?? null, function ($query, string $tier): void {
                $query->whereHas('tier', fn ($query) => $query->where('name', 'like', "%{$tier}%"));
            })
            ->when($filters['trading_status'] ?? null, fn ($query, string $status) => $query->where('trading_status', $status))
            ->latest('updated_at')
            ->latest('id');

        return $query->paginate($filters['per_page'] ?? 15, ['*'], 'page', $filters['page'] ?? 1);
    }

    public function find(int|string $accountId): Account
    {
        return Account::query()
            ->with([
                'user:id,name,email,country,phone,role,status,created_at',
                'tier:id,name,minimum_balance,strategy_id,description,benefits,feature_access,display_settings',
                'tier.strategy:id,name,description,risk_profile,active',
                'performanceSnapshots' => fn ($query) => $query
                    ->select(['id', 'account_id', 'managed_balance', 'total_profit_loss', 'performance_percentage', 'snapshot_at'])
                    ->orderBy('snapshot_at')
                    ->orderBy('id'),
                'transactions' => fn ($query) => $query
                    ->select(['id', 'account_id', 'asset_id', 'type', 'status', 'crypto_amount', 'usd_amount', 'price_snapshot', 'reference', 'description', 'occurred_at'])
                    ->with('asset:id,symbol,name')
                    ->orderByDesc('occurred_at')
                    ->orderByDesc('id'),
                'deposits' => fn ($query) => $query
                    ->select(['id', 'account_id', 'asset_id', 'crypto_amount', 'price_snapshot', 'usd_value', 'transaction_reference', 'status', 'rejection_reason', 'reviewed_at', 'created_at'])
                    ->with('asset:id,symbol,name')
                    ->orderByDesc('created_at')
                    ->orderByDesc('id'),
                'withdrawals' => fn ($query) => $query
                    ->select(['id', 'account_id', 'asset_id', 'amount', 'destination_wallet', 'crypto_amount', 'price_snapshot', 'status', 'rejection_reason', 'reviewed_at', 'created_at'])
                    ->with('asset:id,symbol,name')
                    ->orderByDesc('created_at')
                    ->orderByDesc('id'),
            ])
            ->findOrFail($accountId);
    }
}
