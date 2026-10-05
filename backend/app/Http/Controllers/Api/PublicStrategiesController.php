<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Strategy;
use App\Models\Tier;
use Illuminate\Http\JsonResponse;

class PublicStrategiesController extends Controller
{
    public function index(): JsonResponse
    {
        $strategies = Strategy::query()
            ->where('active', true)
            ->with(['tiers' => fn ($query) => $query->orderBy('minimum_balance')->orderBy('id')])
            ->whereHas('tiers')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $strategies->map(fn (Strategy $strategy): array => $this->strategyData($strategy))->values(),
        ]);
    }

    public function show(int $strategy): JsonResponse
    {
        $strategy = Strategy::query()
            ->where('active', true)
            ->with(['tiers' => fn ($query) => $query->orderBy('minimum_balance')->orderBy('id')])
            ->whereHas('tiers')
            ->findOrFail($strategy);

        return response()->json(['data' => $this->strategyData($strategy)]);
    }

    private function strategyData(Strategy $strategy): array
    {
        return [
            'id' => $strategy->id,
            'name' => $strategy->name,
            'description' => $strategy->description,
            'risk_profile' => $strategy->risk_profile,
            'tiers' => $strategy->tiers->map(fn (Tier $tier): array => [
                'id' => $tier->id,
                'name' => $tier->name,
                'minimum_balance' => $tier->minimum_balance,
                'description' => $tier->description,
                'benefits' => $tier->benefits ?? [],
                'features' => $tier->feature_access ?? [],
            ])->values(),
        ];
    }
}
