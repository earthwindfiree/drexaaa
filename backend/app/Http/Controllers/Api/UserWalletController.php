<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Deposit;
use App\Models\Withdrawal;
use App\Services\WithdrawalAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserWalletController extends Controller
{
    public function show(Request $request, WithdrawalAvailabilityService $withdrawalAvailability): JsonResponse
    {
        $account = $request->user()->account()->firstOrFail();
        $assets = Asset::query()
            ->where('active', true)
            ->with([
                'marketPrice:id,asset_id,current_price,change_24h_percentage',
                'wallets' => fn ($query) => $query->where('active', true)->orderBy('id'),
            ])
            ->orderBy('symbol')
            ->get();

        return response()->json([
            'data' => [
                'account' => [
                    'managed_balance' => $account->managed_balance,
                    'pending_balance' => $account->pending_balance,
                    'withdrawable_amount' => $withdrawalAvailability->withdrawableAmount($account),
                ],
                'assets' => $assets->map(fn (Asset $asset): array => [
                    'id' => $asset->id,
                    'symbol' => $asset->symbol,
                    'name' => $asset->name,
                    'market_price' => $asset->marketPrice ? [
                        'current_price' => $asset->marketPrice->current_price,
                        'change_24h_percentage' => $asset->marketPrice->change_24h_percentage,
                    ] : null,
                    'wallets' => $asset->wallets->map(fn ($wallet): array => [
                        'network' => $wallet->network,
                        'wallet_address' => $wallet->wallet_address,
                    ])->values(),
                ])->values(),
                'deposits' => $account->deposits()->with('asset:id,symbol,name', 'assetWallet:id,network,wallet_address')
                    ->orderByDesc('created_at')->orderByDesc('id')->get()
                    ->map(fn (Deposit $deposit): array => [
                        'id' => $deposit->id,
                        'asset' => $deposit->asset ? ['id' => $deposit->asset->id, 'symbol' => $deposit->asset->symbol, 'name' => $deposit->asset->name] : null,
                        'crypto_amount' => $deposit->crypto_amount,
                        'price_snapshot' => $deposit->price_snapshot,
                        'usd_value' => $deposit->usd_value,
                        'wallet' => $deposit->assetWallet ? ['network' => $deposit->assetWallet->network, 'wallet_address' => $deposit->assetWallet->wallet_address] : null,
                        'transaction_reference' => $deposit->transaction_reference,
                        'status' => $deposit->status->value,
                        'rejection_reason' => $deposit->rejection_reason,
                        'submitted_at' => $deposit->created_at?->toISOString(),
                        'reviewed_at' => $deposit->reviewed_at?->toISOString(),
                    ])->values(),
                'withdrawals' => $account->withdrawals()->with('asset:id,symbol,name')
                    ->orderByDesc('created_at')->orderByDesc('id')->get()
                    ->map(fn (Withdrawal $withdrawal): array => [
                        'id' => $withdrawal->id,
                        'asset' => $withdrawal->asset ? ['id' => $withdrawal->asset->id, 'symbol' => $withdrawal->asset->symbol, 'name' => $withdrawal->asset->name] : null,
                        'amount' => $withdrawal->amount,
                        'destination_wallet' => $withdrawal->destination_wallet,
                        'crypto_amount' => $withdrawal->crypto_amount,
                        'price_snapshot' => $withdrawal->price_snapshot,
                        'status' => $withdrawal->status->value,
                        'rejection_reason' => $withdrawal->rejection_reason,
                        'submitted_at' => $withdrawal->created_at?->toISOString(),
                        'reviewed_at' => $withdrawal->reviewed_at?->toISOString(),
                    ])->values(),
            ],
        ]);
    }
}
