<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Services\AdminDepositService;
use App\Services\DepositReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDepositsController extends Controller
{
    public function index(Request $request, AdminDepositService $depositService): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,confirmed,rejected'],
            'asset_id' => ['nullable', 'integer', 'exists:assets,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $deposits = $depositService->paginate($filters);

        return response()->json([
            'data' => $deposits->getCollection()->map(fn (Deposit $deposit): array => $this->listItem($deposit))->values(),
            'meta' => [
                'current_page' => $deposits->currentPage(),
                'last_page' => $deposits->lastPage(),
                'per_page' => $deposits->perPage(),
                'from' => $deposits->firstItem(),
                'to' => $deposits->lastItem(),
                'total' => $deposits->total(),
            ],
        ]);
    }

    public function show(Deposit $deposit, AdminDepositService $depositService): JsonResponse
    {
        return response()->json(['data' => $this->detail($depositService->find($deposit->getKey()))]);
    }

    public function confirm(Request $request, Deposit $deposit, DepositReviewService $reviewService, AdminDepositService $depositService): JsonResponse
    {
        $reviewService->confirm($deposit, $request->user());

        return response()->json(['data' => $this->detail($depositService->find($deposit->getKey()))]);
    }

    public function reject(Request $request, Deposit $deposit, DepositReviewService $reviewService, AdminDepositService $depositService): JsonResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);
        $reviewService->reject($deposit, $request->user(), $validated['rejection_reason']);

        return response()->json(['data' => $this->detail($depositService->find($deposit->getKey()))]);
    }

    private function listItem(Deposit $deposit): array
    {
        return [
            'id' => $deposit->id,
            'user' => $deposit->user ? [
                'id' => $deposit->user->id,
                'name' => $deposit->user->name,
                'email' => $deposit->user->email,
            ] : null,
            'asset' => $deposit->asset ? [
                'id' => $deposit->asset->id,
                'symbol' => $deposit->asset->symbol,
                'name' => $deposit->asset->name,
            ] : null,
            'wallet' => $deposit->assetWallet ? [
                'id' => $deposit->assetWallet->id,
                'network' => $deposit->assetWallet->network,
                'wallet_address' => $deposit->assetWallet->wallet_address,
            ] : null,
            'crypto_amount' => $deposit->crypto_amount,
            'price_snapshot' => $deposit->price_snapshot,
            'usd_value' => $deposit->usd_value,
            'transaction_reference' => $deposit->transaction_reference,
            'status' => $deposit->status->value,
            'reviewer' => $deposit->reviewer ? [
                'id' => $deposit->reviewer->id,
                'name' => $deposit->reviewer->name,
            ] : null,
            'created_at' => $deposit->created_at?->toISOString(),
            'reviewed_at' => $deposit->reviewed_at?->toISOString(),
        ];
    }

    private function detail(Deposit $deposit): array
    {
        return array_merge($this->listItem($deposit), [
            'account' => $deposit->account ? [
                'id' => $deposit->account->id,
                'managed_balance' => $deposit->account->managed_balance,
                'pending_balance' => $deposit->account->pending_balance,
                'total_profit_loss' => $deposit->account->total_profit_loss,
                'performance_percentage' => $deposit->account->performance_percentage,
                'trading_status' => $deposit->account->trading_status,
                'tier' => $deposit->account->tier ? [
                    'id' => $deposit->account->tier->id,
                    'name' => $deposit->account->tier->name,
                ] : null,
            ] : null,
            'user' => $deposit->user ? [
                'id' => $deposit->user->id,
                'name' => $deposit->user->name,
                'email' => $deposit->user->email,
                'country' => $deposit->user->country,
                'phone' => $deposit->user->phone,
                'role' => $deposit->user->role,
                'status' => $deposit->user->status,
            ] : null,
            'transaction' => $deposit->transaction ? [
                'id' => $deposit->transaction->id,
                'type' => $deposit->transaction->type->value,
                'status' => $deposit->transaction->status->value,
                'asset' => $deposit->transaction->asset ? [
                    'id' => $deposit->transaction->asset->id,
                    'symbol' => $deposit->transaction->asset->symbol,
                    'name' => $deposit->transaction->asset->name,
                ] : null,
                'crypto_amount' => $deposit->transaction->crypto_amount,
                'usd_amount' => $deposit->transaction->usd_amount,
                'price_snapshot' => $deposit->transaction->price_snapshot,
                'reference' => $deposit->transaction->reference,
                'description' => $deposit->transaction->description,
                'occurred_at' => $deposit->transaction->occurred_at?->toISOString(),
            ] : null,
            'rejection_reason' => $deposit->rejection_reason,
        ]);
    }
}
