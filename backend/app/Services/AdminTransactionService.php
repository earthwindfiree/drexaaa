<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminTransactionService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Transaction::query()
            ->select([
                'id',
                'user_id',
                'account_id',
                'type',
                'status',
                'asset_id',
                'deposit_id',
                'withdrawal_id',
                'crypto_amount',
                'usd_amount',
                'price_snapshot',
                'reference',
                'description',
                'occurred_at',
                'created_at',
            ])
            ->with([
                'user:id,name,email',
                'account:id,user_id',
                'asset:id,symbol,name',
                'deposit:id,transaction_reference,status',
                'withdrawal:id,amount,status,destination_wallet',
            ])
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['asset_id'] ?? null, fn ($query, int $assetId) => $query->where('asset_id', $assetId))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->whereHas('user', function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        return $query->paginate($filters['per_page'] ?? 15, ['*'], 'page', $filters['page'] ?? 1);
    }
}
