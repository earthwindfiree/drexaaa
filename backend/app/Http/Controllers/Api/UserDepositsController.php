<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Services\DepositSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserDepositsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,confirmed,rejected'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $deposits = $request->user()->deposits()
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->with(['asset:id,symbol,name', 'assetWallet:id,asset_id,network,wallet_address'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15, ['*'], 'page', $filters['page'] ?? 1);

        return response()->json([
            'data' => $deposits->getCollection()->map(fn (Deposit $deposit): array => $this->serialize($deposit))->values(),
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

    public function store(Request $request, DepositSubmissionService $submissionService): JsonResponse
    {
        $allowedFields = ['asset_id', 'crypto_amount', 'transaction_reference'];

        if (array_diff(array_keys($request->all()), $allowedFields) !== []) {
            throw ValidationException::withMessages(['deposit' => ['Only asset, crypto amount, and transaction reference may be submitted.']]);
        }

        $validated = $request->validate([
            'asset_id' => ['required', 'integer'],
            'crypto_amount' => ['required', 'string', 'max:40'],
            'transaction_reference' => ['required', 'string', 'max:255'],
        ]);
        $deposit = $submissionService->submit(
            $request->user(),
            (int) $validated['asset_id'],
            $validated['crypto_amount'],
            $validated['transaction_reference'],
        );

        return response()->json(['data' => $this->serialize($deposit->load(['asset:id,symbol,name', 'assetWallet:id,asset_id,network,wallet_address']))], 201);
    }

    public function show(Request $request, int $deposit): JsonResponse
    {
        $record = $request->user()->deposits()
            ->with(['asset:id,symbol,name', 'assetWallet:id,asset_id,network,wallet_address'])
            ->findOrFail($deposit);

        return response()->json(['data' => $this->serialize($record)]);
    }

    private function serialize(Deposit $deposit): array
    {
        return [
            'id' => $deposit->id,
            'asset' => $deposit->asset ? [
                'id' => $deposit->asset->id,
                'symbol' => $deposit->asset->symbol,
                'name' => $deposit->asset->name,
            ] : null,
            'wallet' => $deposit->assetWallet ? [
                'network' => $deposit->assetWallet->network,
                'wallet_address' => $deposit->assetWallet->wallet_address,
            ] : null,
            'crypto_amount' => $deposit->crypto_amount,
            'price_snapshot' => $deposit->price_snapshot,
            'usd_value' => $deposit->usd_value,
            'transaction_reference' => $deposit->transaction_reference,
            'status' => $deposit->status->value,
            'rejection_reason' => $deposit->rejection_reason,
            'submitted_at' => $deposit->created_at?->toISOString(),
            'reviewed_at' => $deposit->reviewed_at?->toISOString(),
        ];
    }
}
