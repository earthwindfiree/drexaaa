<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\AdminWithdrawalService;
use App\Services\WithdrawalAvailabilityService;
use App\Services\WithdrawalReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminWithdrawalsController extends Controller
{
    public function index(Request $request, AdminWithdrawalService $withdrawalService): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,approved,rejected'],
            'asset_id' => ['nullable', 'integer', 'exists:assets,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $withdrawals = $withdrawalService->paginate($filters);

        return response()->json([
            'data' => $withdrawals->getCollection()->map(fn (Withdrawal $withdrawal): array => $this->listItem($withdrawal))->values(),
            'meta' => [
                'current_page' => $withdrawals->currentPage(),
                'last_page' => $withdrawals->lastPage(),
                'per_page' => $withdrawals->perPage(),
                'from' => $withdrawals->firstItem(),
                'to' => $withdrawals->lastItem(),
                'total' => $withdrawals->total(),
            ],
        ]);
    }

    public function show(Withdrawal $withdrawal, AdminWithdrawalService $withdrawalService, WithdrawalAvailabilityService $availabilityService): JsonResponse
    {
        $withdrawal = $withdrawalService->find($withdrawal->getKey());

        return response()->json(['data' => $this->detail($withdrawal, $availabilityService)]);
    }

    public function approve(Request $request, Withdrawal $withdrawal, WithdrawalReviewService $reviewService, AdminWithdrawalService $withdrawalService, WithdrawalAvailabilityService $availabilityService): JsonResponse
    {
        $reviewService->approve($withdrawal, $request->user());
        $updated = $withdrawalService->find($withdrawal->getKey());

        return response()->json(['data' => $this->detail($updated, $availabilityService)]);
    }

    public function reject(Request $request, Withdrawal $withdrawal, WithdrawalReviewService $reviewService, AdminWithdrawalService $withdrawalService, WithdrawalAvailabilityService $availabilityService): JsonResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);
        $reviewService->reject($withdrawal, $request->user(), $validated['rejection_reason']);
        $updated = $withdrawalService->find($withdrawal->getKey());

        return response()->json(['data' => $this->detail($updated, $availabilityService)]);
    }

    private function listItem(Withdrawal $withdrawal): array
    {
        return [
            'id' => $withdrawal->id,
            'user' => $withdrawal->user ? [
                'id' => $withdrawal->user->id,
                'name' => $withdrawal->user->name,
                'email' => $withdrawal->user->email,
            ] : null,
            'account_id' => $withdrawal->account_id,
            'asset' => $withdrawal->asset ? [
                'id' => $withdrawal->asset->id,
                'symbol' => $withdrawal->asset->symbol,
                'name' => $withdrawal->asset->name,
            ] : null,
            'amount' => $withdrawal->amount,
            'destination_wallet' => $withdrawal->destination_wallet,
            'crypto_amount' => $withdrawal->crypto_amount,
            'price_snapshot' => $withdrawal->price_snapshot,
            'status' => $withdrawal->status->value,
            'transaction' => $withdrawal->transaction ? [
                'id' => $withdrawal->transaction->id,
                'reference' => $withdrawal->transaction->reference,
            ] : null,
            'reviewer' => $withdrawal->reviewer ? [
                'id' => $withdrawal->reviewer->id,
                'name' => $withdrawal->reviewer->name,
            ] : null,
            'reviewed_at' => $withdrawal->reviewed_at?->toISOString(),
            'rejection_reason' => $withdrawal->rejection_reason,
            'created_at' => $withdrawal->created_at?->toISOString(),
        ];
    }

    private function detail(Withdrawal $withdrawal, WithdrawalAvailabilityService $availabilityService): array
    {
        return array_merge($this->listItem($withdrawal), [
            'account' => $withdrawal->account ? [
                'id' => $withdrawal->account->id,
                'managed_balance' => $withdrawal->account->managed_balance,
                'pending_balance' => $withdrawal->account->pending_balance,
                'total_profit_loss' => $withdrawal->account->total_profit_loss,
                'performance_percentage' => $withdrawal->account->performance_percentage,
                'trading_status' => $withdrawal->account->trading_status,
                'tier' => $withdrawal->account->tier ? [
                    'id' => $withdrawal->account->tier->id,
                    'name' => $withdrawal->account->tier->name,
                ] : null,
                'withdrawable_amount' => $availabilityService->withdrawableAmount($withdrawal->account),
            ] : null,
            'user' => $withdrawal->user ? [
                'id' => $withdrawal->user->id,
                'name' => $withdrawal->user->name,
                'email' => $withdrawal->user->email,
                'country' => $withdrawal->user->country,
                'phone' => $withdrawal->user->phone,
                'role' => $withdrawal->user->role,
                'status' => $withdrawal->user->status,
            ] : null,
            'transaction' => $withdrawal->transaction ? [
                'id' => $withdrawal->transaction->id,
                'type' => $withdrawal->transaction->type->value,
                'status' => $withdrawal->transaction->status->value,
                'asset' => $withdrawal->transaction->asset ? [
                    'id' => $withdrawal->transaction->asset->id,
                    'symbol' => $withdrawal->transaction->asset->symbol,
                    'name' => $withdrawal->transaction->asset->name,
                ] : null,
                'crypto_amount' => $withdrawal->transaction->crypto_amount,
                'usd_amount' => $withdrawal->transaction->usd_amount,
                'price_snapshot' => $withdrawal->transaction->price_snapshot,
                'reference' => $withdrawal->transaction->reference,
                'description' => $withdrawal->transaction->description,
                'occurred_at' => $withdrawal->transaction->occurred_at?->toISOString(),
            ] : null,
        ]);
    }
}
