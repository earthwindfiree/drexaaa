<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\JsonResponse;

class UserMarketsController extends Controller
{
    public function index(): JsonResponse
    {
        $assets = Asset::query()
            ->where('active', true)
            ->with('marketPrice:id,asset_id,current_price,change_24h_percentage')
            ->orderBy('symbol')
            ->get();

        return response()->json([
            'data' => $assets->map(fn (Asset $asset): array => [
                'id' => $asset->id,
                'symbol' => $asset->symbol,
                'name' => $asset->name,
                'active' => $asset->active,
                'market_price' => $asset->marketPrice ? [
                    'current_price' => $asset->marketPrice->current_price,
                    'change_24h_percentage' => $asset->marketPrice->change_24h_percentage,
                ] : null,
            ])->values(),
        ]);
    }
}
