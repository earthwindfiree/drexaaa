<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetWallet;
use App\Services\AdminMarketConfigurationService;
use App\Services\MarketPriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminMarketsController extends Controller
{
    public function index(AdminMarketConfigurationService $configurationService): JsonResponse
    {
        return response()->json([
            'data' => $configurationService->assets()->map(fn (Asset $asset): array => $this->assetData($asset))->values(),
        ]);
    }

    public function updateAssetStatus(Request $request, Asset $asset, AdminMarketConfigurationService $configurationService): JsonResponse
    {
        $this->assertAllowedFields($request, ['active']);
        $changes = $request->validate(['active' => ['required', 'boolean']]);
        $updated = $configurationService->updateAssetStatus($asset, $request->user(), (bool) $changes['active']);

        return response()->json(['data' => $this->assetData($updated->load(['marketPrice', 'wallets']))]);
    }

    public function updatePrice(Request $request, Asset $asset, MarketPriceService $marketPriceService): JsonResponse
    {
        $this->assertAllowedFields($request, ['current_price', 'change_24h_percentage']);
        $changes = $request->validate([
            'current_price' => ['required', 'string', 'regex:/\A\d{1,12}(?:\.\d{1,8})?\z/'],
            'change_24h_percentage' => ['required', 'string', 'regex:/\A-?\d{1,5}(?:\.\d{1,4})?\z/'],
        ]);
        $marketPrice = $marketPriceService->update($request->user(), $asset, $changes['current_price'], $changes['change_24h_percentage']);

        return response()->json([
            'data' => [
                'id' => $marketPrice->id,
                'asset_id' => $marketPrice->asset_id,
                'current_price' => $marketPrice->current_price,
                'change_24h_percentage' => $marketPrice->change_24h_percentage,
            ],
        ]);
    }

    public function wallets(Asset $asset, AdminMarketConfigurationService $configurationService): JsonResponse
    {
        return response()->json([
            'data' => $configurationService->wallets($asset)->map(fn (AssetWallet $wallet): array => $this->walletData($wallet))->values(),
        ]);
    }

    public function storeWallet(Request $request, Asset $asset, AdminMarketConfigurationService $configurationService): JsonResponse
    {
        $this->assertAllowedFields($request, ['wallet_address', 'network', 'active']);
        $changes = $request->validate([
            'wallet_address' => ['required', 'string', 'max:255'],
            'network' => ['nullable', 'string', 'max:100'],
            'active' => ['required', 'boolean'],
        ]);
        $wallet = $configurationService->createWallet($asset, $request->user(), $changes['wallet_address'], $changes['network'] ?? null, (bool) $changes['active']);

        return response()->json(['data' => $this->walletData($wallet)], 201);
    }

    public function updateWalletStatus(Request $request, AssetWallet $wallet, AdminMarketConfigurationService $configurationService): JsonResponse
    {
        $this->assertAllowedFields($request, ['active']);
        $changes = $request->validate(['active' => ['required', 'boolean']]);
        $updated = $configurationService->updateWalletStatus($wallet, $request->user(), (bool) $changes['active']);

        return response()->json(['data' => $this->walletData($updated->load('asset'))]);
    }

    private function assetData(Asset $asset): array
    {
        return [
            'id' => $asset->id,
            'symbol' => $asset->symbol,
            'name' => $asset->name,
            'active' => $asset->active,
            'market_price' => $asset->marketPrice ? [
                'id' => $asset->marketPrice->id,
                'current_price' => $asset->marketPrice->current_price,
                'change_24h_percentage' => $asset->marketPrice->change_24h_percentage,
            ] : null,
            'wallets' => $asset->wallets->map(fn (AssetWallet $wallet): array => $this->walletData($wallet))->values(),
            'active_wallet_count' => $asset->wallets->where('active', true)->count(),
            'has_usable_wallet' => $asset->active && $asset->wallets->where('active', true)->count() === 1,
        ];
    }

    private function assertAllowedFields(Request $request, array $allowedFields): void
    {
        if (array_diff(array_keys($request->all()), $allowedFields) !== []) {
            throw ValidationException::withMessages([
                'market_configuration' => ['Only supported configuration fields may be changed.'],
            ]);
        }
    }

    private function walletData(AssetWallet $wallet): array
    {
        return [
            'id' => $wallet->id,
            'asset_id' => $wallet->asset_id,
            'network' => $wallet->network,
            'wallet_address' => $wallet->wallet_address,
            'active' => $wallet->active,
            'created_at' => $wallet->created_at?->toISOString(),
            'updated_at' => $wallet->updated_at?->toISOString(),
        ];
    }
}
