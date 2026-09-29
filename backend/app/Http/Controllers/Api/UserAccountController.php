<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\PerformanceSnapshot;
use App\Models\Transaction;
use App\Services\PerformanceHistoryService;
use App\Services\TransactionHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserAccountController extends Controller
{
    public function dashboard(Request $request, TransactionHistoryService $transactionHistory): JsonResponse
    {
        $account = $this->account($request);
        $recentTransactions = $transactionHistory
            ->forUser($request->user(), accountId: $account->getKey())
            ->with('asset:id,symbol,name')
            ->limit(5)
            ->get();

        return response()->json([
            'data' => [
                'account' => $this->accountData($account),
                'recent_transactions' => $recentTransactions->map(fn (Transaction $transaction): array => $this->transactionData($transaction))->values(),
            ],
        ]);
    }

    public function portfolio(Request $request, PerformanceHistoryService $performanceHistory): JsonResponse
    {
        $account = $this->account($request);

        return response()->json([
            'data' => [
                ...$this->accountData($account),
                'performance_history' => $performanceHistory->forAccount($account)->get()
                    ->map(fn (PerformanceSnapshot $snapshot): array => [
                        'id' => $snapshot->id,
                        'account_value' => $snapshot->managed_balance,
                        'profit_loss' => $snapshot->total_profit_loss,
                        'performance_percentage' => $snapshot->performance_percentage,
                        'snapshot_at' => $snapshot->snapshot_at?->toISOString(),
                    ])->values(),
            ],
        ]);
    }

    private function account(Request $request): Account
    {
        return $request->user()->account()->with('tier.strategy')->firstOrFail();
    }

    private function accountData(Account $account): array
    {
        return [
            'id' => $account->id,
            'managed_balance' => $account->managed_balance,
            'pending_balance' => $account->pending_balance,
            'total_profit_loss' => $account->total_profit_loss,
            'performance_percentage' => $account->performance_percentage,
            'trading_status' => $account->trading_status,
            'tier' => $account->tier ? [
                'id' => $account->tier->id,
                'name' => $account->tier->name,
                'minimum_balance' => $account->tier->minimum_balance,
                'description' => $account->tier->description,
                'benefits' => $account->tier->benefits,
                'feature_access' => $account->tier->feature_access,
                'display_settings' => $account->tier->display_settings,
                'strategy' => $account->tier->strategy ? [
                    'id' => $account->tier->strategy->id,
                    'name' => $account->tier->strategy->name,
                    'description' => $account->tier->strategy->description,
                    'risk_profile' => $account->tier->strategy->risk_profile,
                    'active' => $account->tier->strategy->active,
                ] : null,
            ] : null,
        ];
    }

    private function transactionData(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'type' => $transaction->type->value,
            'status' => $transaction->status->value,
            'asset' => $transaction->asset ? [
                'id' => $transaction->asset->id,
                'symbol' => $transaction->asset->symbol,
                'name' => $transaction->asset->name,
            ] : null,
            'crypto_amount' => $transaction->crypto_amount,
            'usd_amount' => $transaction->usd_amount,
            'price_snapshot' => $transaction->price_snapshot,
            'reference' => $transaction->reference,
            'description' => $transaction->description,
            'occurred_at' => $transaction->occurred_at?->toISOString(),
        ];
    }
}
