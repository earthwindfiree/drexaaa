<?php

namespace App\Services;

use App\Models\Deposit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminDepositService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Deposit::query()
            ->select([
                'id',
                'user_id',
                'account_id',
                'asset_id',
                'asset_wallet_id',
                'crypto_amount',
                'price_snapshot',
                'usd_value',
                'transaction_reference',
                'status',
                'reviewed_by',
                'reviewed_at',
                'created_at',
            ])
            ->with([
                'user:id,name,email',
                'asset:id,symbol,name',
                'assetWallet:id,asset_id,network,wallet_address',
                'reviewer:id,name,email',
            ])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['asset_id'] ?? null, fn ($query, int $assetId) => $query->where('asset_id', $assetId))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->whereHas('user', function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest('created_at')
            ->latest('id');

        return $query->paginate($filters['per_page'] ?? 15, ['*'], 'page', $filters['page'] ?? 1);
    }

    public function find(int|string $depositId): Deposit
    {
        return Deposit::query()
            ->with([
                'user:id,name,email,country,phone,role,status',
                'account:id,user_id,managed_balance,pending_balance,total_profit_loss,performance_percentage,trading_status,tier_id',
                'account.tier:id,name',
                'asset:id,symbol,name,active',
                'assetWallet:id,asset_id,network,wallet_address,active',
                'reviewer:id,name,email',
                'transaction:id,user_id,account_id,asset_id,deposit_id,type,status,crypto_amount,usd_amount,price_snapshot,reference,description,occurred_at',
                'transaction.asset:id,symbol,name',
            ])
            ->findOrFail($depositId);
    }
}
