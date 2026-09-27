<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\AdminTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTransactionsController extends Controller
{
    public function index(Request $request, AdminTransactionService $transactionService): JsonResponse
    {
        $filters = $request->validate([
            'type' => ['nullable', 'string', 'in:deposit,withdrawal,adjustment'],
            'status' => ['nullable', 'string', 'in:completed,pending,rejected'],
            'asset_id' => ['nullable', 'integer', 'exists:assets,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $transactions = $transactionService->paginate($filters);

        return response()->json([
            'data' => $transactions->getCollection()->map(fn (Transaction $transaction): array => $this->serialize($transaction))->values(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'from' => $transactions->firstItem(),
                'to' => $transactions->lastItem(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    private function serialize(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'user' => $transaction->user ? [
                'id' => $transaction->user->id,
                'name' => $transaction->user->name,
                'email' => $transaction->user->email,
            ] : null,
            'account' => $transaction->account ? ['id' => $transaction->account->id] : null,
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
            'related_deposit' => $transaction->deposit ? [
                'id' => $transaction->deposit->id,
                'reference' => $transaction->deposit->transaction_reference,
                'status' => $transaction->deposit->status->value,
            ] : null,
            'related_withdrawal' => $transaction->withdrawal ? [
                'id' => $transaction->withdrawal->id,
                'amount' => $transaction->withdrawal->amount,
                'status' => $transaction->withdrawal->status->value,
                'destination_wallet' => $transaction->withdrawal->destination_wallet,
            ] : null,
            'occurred_at' => $transaction->occurred_at?->toISOString(),
            'created_at' => $transaction->created_at?->toISOString(),
        ];
    }
}
