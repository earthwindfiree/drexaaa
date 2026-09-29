<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\WithdrawalAvailabilityService;
use App\Services\WithdrawalSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserWithdrawalsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,approved,rejected'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $withdrawals = $request->user()->withdrawals()
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->with('asset:id,symbol,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15, ['*'], 'page', $filters['page'] ?? 1);

        return response()->json([
            'data' => $withdrawals->getCollection()->map(fn (Withdrawal $withdrawal): array => $this->serialize($withdrawal))->values(),
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

    public function store(Request $request, WithdrawalSubmissionService $submissionService): JsonResponse
    {
        $allowedFields = ['asset_id', 'amount', 'destination_wallet'];

        if (array_diff(array_keys($request->all()), $allowedFields) !== []) {
            throw ValidationException::withMessages(['withdrawal' => ['Only asset, USD amount, and destination wallet may be submitted.']]);
        }

        $validated = $request->validate([
            'asset_id' => ['required', 'integer'],
            'amount' => ['required', 'string', 'max:21'],
            'destination_wallet' => ['required', 'string', 'max:255'],
        ]);
        $withdrawal = $submissionService->submit(
            $request->user(),
            (int) $validated['asset_id'],
            $validated['amount'],
            $validated['destination_wallet'],
        );

        return response()->json(['data' => $this->serialize($withdrawal->load('asset:id,symbol,name'))], 201);
    }

    public function show(Request $request, int $withdrawal, WithdrawalAvailabilityService $availability): JsonResponse
    {
        $record = $request->user()->withdrawals()->with('asset:id,symbol,name')->findOrFail($withdrawal);

        return response()->json(['data' => [
            ...$this->serialize($record),
            'withdrawable_amount' => $availability->withdrawableAmount($record->account),
        ]]);
    }

    private function serialize(Withdrawal $withdrawal): array
    {
        return [
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
            'submitted_at' => $withdrawal->created_at?->toISOString(),
            'reviewed_at' => $withdrawal->reviewed_at?->toISOString(),
        ];
    }
}
