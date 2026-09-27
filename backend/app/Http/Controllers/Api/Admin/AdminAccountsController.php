<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\AdminAccountService;
use App\Services\AdminAccountSimulationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminAccountsController extends Controller
{
    public function index(Request $request, AdminAccountService $accountService): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'tier' => ['nullable', 'string', 'max:100'],
            'trading_status' => ['nullable', 'string', 'max:32'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $accounts = $accountService->paginate($filters);

        return response()->json([
            'data' => $accounts->getCollection()->map(fn (Account $account): array => $this->listItem($account))->values(),
            'meta' => [
                'current_page' => $accounts->currentPage(),
                'last_page' => $accounts->lastPage(),
                'per_page' => $accounts->perPage(),
                'from' => $accounts->firstItem(),
                'to' => $accounts->lastItem(),
                'total' => $accounts->total(),
            ],
        ]);
    }

    public function show(Account $account, AdminAccountService $accountService): JsonResponse
    {
        $account = $accountService->find($account->getKey());

        return response()->json([
            'data' => [
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
                'user' => $account->user ? [
                    'id' => $account->user->id,
                    'name' => $account->user->name,
                    'email' => $account->user->email,
                    'country' => $account->user->country,
                    'phone' => $account->user->phone,
                    'role' => $account->user->role,
                    'status' => $account->user->status,
                    'created_at' => $account->user->created_at?->toISOString(),
                ] : null,
                'performance_snapshots' => $account->performanceSnapshots->map(fn ($snapshot): array => [
                    'id' => $snapshot->id,
                    'managed_balance' => $snapshot->managed_balance,
                    'total_profit_loss' => $snapshot->total_profit_loss,
                    'performance_percentage' => $snapshot->performance_percentage,
                    'snapshot_at' => $snapshot->snapshot_at?->toISOString(),
                ])->values(),
                'transactions' => $account->transactions->map(fn ($transaction): array => [
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
                ])->values(),
                'deposits' => $account->deposits->map(fn ($deposit): array => [
                    'id' => $deposit->id,
                    'asset' => $deposit->asset ? [
                        'id' => $deposit->asset->id,
                        'symbol' => $deposit->asset->symbol,
                        'name' => $deposit->asset->name,
                    ] : null,
                    'crypto_amount' => $deposit->crypto_amount,
                    'price_snapshot' => $deposit->price_snapshot,
                    'usd_value' => $deposit->usd_value,
                    'transaction_reference' => $deposit->transaction_reference,
                    'status' => $deposit->status->value,
                    'rejection_reason' => $deposit->rejection_reason,
                    'reviewed_at' => $deposit->reviewed_at?->toISOString(),
                    'created_at' => $deposit->created_at?->toISOString(),
                ])->values(),
                'withdrawals' => $account->withdrawals->map(fn ($withdrawal): array => [
                    'id' => $withdrawal->id,
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
                    'rejection_reason' => $withdrawal->rejection_reason,
                    'reviewed_at' => $withdrawal->reviewed_at?->toISOString(),
                    'created_at' => $withdrawal->created_at?->toISOString(),
                ])->values(),
                'created_at' => $account->created_at?->toISOString(),
                'updated_at' => $account->updated_at?->toISOString(),
            ],
        ]);
    }

    public function updateSimulation(Request $request, Account $account, AdminAccountSimulationService $simulationService): JsonResponse
    {
        $allowedFields = ['managed_balance', 'total_profit_loss', 'performance_percentage', 'trading_status'];
        $unknownFields = array_diff(array_keys($request->all()), $allowedFields);

        if ($unknownFields !== []) {
            throw ValidationException::withMessages([
                'account' => ['Only supported simulation fields may be changed.'],
            ]);
        }

        $changes = $request->validate([
            'managed_balance' => ['sometimes', 'string', 'required_without_all:total_profit_loss,performance_percentage,trading_status', 'regex:/\A\d{1,18}(?:\.\d{1,2})?\z/'],
            'total_profit_loss' => ['sometimes', 'string', 'required_without_all:managed_balance,performance_percentage,trading_status', 'regex:/\A-?\d{1,18}(?:\.\d{1,2})?\z/'],
            'performance_percentage' => ['sometimes', 'string', 'required_without_all:managed_balance,total_profit_loss,trading_status', 'regex:/\A-?\d{1,5}(?:\.\d{1,4})?\z/'],
            'trading_status' => ['sometimes', 'string', 'required_without_all:managed_balance,total_profit_loss,performance_percentage', 'in:inactive,active'],
            'pending_balance' => ['prohibited'],
            'tier_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'role' => ['prohibited'],
            'status' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ]);

        if ($changes === []) {
            throw ValidationException::withMessages([
                'account' => ['At least one simulation field must be provided.'],
            ]);
        }

        $updated = $simulationService->update($account, $request->user(), $changes);

        return response()->json([
            'data' => [
                'id' => $updated->id,
                'managed_balance' => $updated->managed_balance,
                'pending_balance' => $updated->pending_balance,
                'total_profit_loss' => $updated->total_profit_loss,
                'performance_percentage' => $updated->performance_percentage,
                'trading_status' => $updated->trading_status,
                'tier_id' => $updated->tier_id,
            ],
        ]);
    }

    private function listItem(Account $account): array
    {
        return [
            'id' => $account->id,
            'user' => $account->user ? [
                'id' => $account->user->id,
                'name' => $account->user->name,
                'email' => $account->user->email,
            ] : null,
            'managed_balance' => $account->managed_balance,
            'pending_balance' => $account->pending_balance,
            'total_profit_loss' => $account->total_profit_loss,
            'performance_percentage' => $account->performance_percentage,
            'tier' => $account->tier ? [
                'id' => $account->tier->id,
                'name' => $account->tier->name,
            ] : null,
            'trading_status' => $account->trading_status,
            'created_at' => $account->created_at?->toISOString(),
            'updated_at' => $account->updated_at?->toISOString(),
        ];
    }
}
