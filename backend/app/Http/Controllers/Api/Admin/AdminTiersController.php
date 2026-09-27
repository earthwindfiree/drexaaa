<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tier;
use App\Services\AdminTierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminTiersController extends Controller
{
    public function index(AdminTierService $tierService): JsonResponse
    {
        $configuration = $tierService->list();

        return response()->json([
            'data' => [
                'tiers' => collect($configuration['tiers'])->map(fn (Tier $tier): array => $this->serializeTier($tier))->values(),
                'strategies' => collect($configuration['strategies'])->map(fn ($strategy): array => [
                    'id' => $strategy->id,
                    'name' => $strategy->name,
                    'description' => $strategy->description,
                    'risk_profile' => $strategy->risk_profile,
                    'active' => $strategy->active,
                ])->values(),
            ],
        ]);
    }

    public function update(Request $request, Tier $tier, AdminTierService $tierService): JsonResponse
    {
        $allowedFields = ['name', 'minimum_balance', 'strategy_id', 'description', 'benefits', 'feature_access', 'display_settings'];
        if (array_diff(array_keys($request->all()), $allowedFields) !== []) {
            throw ValidationException::withMessages(['tier' => ['Only supported tier configuration fields may be changed.']]);
        }

        $changes = $request->validate([
            'name' => ['sometimes', 'string', 'required', 'max:255'],
            'minimum_balance' => ['sometimes', 'string', 'required', 'regex:/\A\d{1,18}(?:\.\d{1,2})?\z/'],
            'strategy_id' => ['sometimes', 'integer', 'required', 'exists:strategies,id'],
            'description' => ['sometimes', 'string', 'required', 'max:10000'],
            'benefits' => ['sometimes', 'nullable', 'array'],
            'feature_access' => ['sometimes', 'nullable', 'array'],
            'display_settings' => ['sometimes', 'nullable', 'array'],
        ]);

        if ($changes === []) {
            throw ValidationException::withMessages(['tier' => ['At least one tier configuration field is required.']]);
        }

        $updated = $tierService->update($tier, $request->user(), $changes);

        return response()->json(['data' => $this->serializeTier($updated)], 200);
    }

    private function serializeTier(Tier $tier): array
    {
        return [
            'id' => $tier->id,
            'name' => $tier->name,
            'minimum_balance' => $tier->minimum_balance,
            'description' => $tier->description,
            'benefits' => $tier->benefits,
            'feature_access' => $tier->feature_access,
            'display_settings' => $tier->display_settings,
            'strategy' => $tier->strategy ? [
                'id' => $tier->strategy->id,
                'name' => $tier->strategy->name,
                'description' => $tier->strategy->description,
                'risk_profile' => $tier->strategy->risk_profile,
                'active' => $tier->strategy->active,
            ] : null,
        ];
    }
}
