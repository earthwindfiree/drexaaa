<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicMarketHistoryController extends Controller
{
    private const RANGE_HOURS = [
        '24h' => 24,
        '7d' => 168,
        '30d' => 720,
    ];

    public function show(Request $request, int $asset): JsonResponse
    {
        $filters = $request->validate([
            'range' => ['sometimes', 'string', 'in:24h,7d,30d'],
            'limit' => ['sometimes', 'integer', 'min:2', 'max:200'],
        ]);
        $range = $filters['range'] ?? '30d';
        $asset = Asset::query()
            ->where('active', true)
            ->with('marketPrice:id,asset_id,current_price')
            ->findOrFail($asset);
        $rangeStart = now()->subHours(self::RANGE_HOURS[$range])->startOfHour();

        $points = $asset->marketHistoryPoints()
            ->where('recorded_at', '>=', $rangeStart)
            ->where('recorded_at', '<=', now())
            ->orderByDesc('recorded_at')
            ->limit($filters['limit'] ?? 200)
            ->get(['price', 'recorded_at'])
            ->reverse()
            ->values();

        return response()->json([
            'data' => [
                'asset' => [
                    'id' => $asset->id,
                    'symbol' => $asset->symbol,
                    'name' => $asset->name,
                    'current_price' => $asset->marketPrice?->current_price,
                ],
                'range' => $range,
                'points' => $points->map(fn ($point): array => [
                    'timestamp' => $point->recorded_at->toISOString(),
                    'price' => $point->price,
                ])->values(),
            ],
        ]);
    }
}
