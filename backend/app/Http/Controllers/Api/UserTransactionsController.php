<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\TransactionHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserTransactionsController extends Controller
{
    public function index(Request $request, TransactionHistoryService $history): JsonResponse
    {
        $filters = $request->validate([
            'type' => ['nullable', 'string', Rule::in(array_column(TransactionType::cases(), 'value'))],
            'status' => ['nullable', 'string', 'in:completed,pending,rejected'],
            'asset_id' => ['nullable', 'integer', 'exists:assets,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $accountId = $request->user()->account()->value('id');
        $type = isset($filters['type']) ? TransactionType::from($filters['type']) : null;
        $transactions = $history->forUser($request->user(), $type, $accountId)
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['asset_id'] ?? null, fn ($query, int $assetId) => $query->where('asset_id', $assetId))
            ->with('asset:id,symbol,name')
            ->paginate($filters['per_page'] ?? 15, ['*'], 'page', $filters['page'] ?? 1);

        return response()->json([
            'data' => $transactions->getCollection()->map(fn (Transaction $transaction): array => [
                'id' => $transaction->id,
                'type' => $transaction->type->value,
                'status' => $transaction->status->value,
                'asset' => $transaction->asset ? ['id' => $transaction->asset->id, 'symbol' => $transaction->asset->symbol, 'name' => $transaction->asset->name] : null,
                'crypto_amount' => $transaction->crypto_amount,
                'usd_amount' => $transaction->usd_amount,
                'price_snapshot' => $transaction->price_snapshot,
                'reference' => $transaction->reference,
                'description' => $transaction->description,
                'occurred_at' => $transaction->occurred_at?->toISOString(),
            ])->values(),
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
}
