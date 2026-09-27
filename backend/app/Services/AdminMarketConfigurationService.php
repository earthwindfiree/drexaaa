<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetWallet;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AdminMarketConfigurationService
{
    public function assets(): Collection
    {
        return Asset::query()
            ->with([
                'marketPrice:id,asset_id,current_price,change_24h_percentage',
                'wallets:id,asset_id,network,wallet_address,active',
            ])
            ->orderBy('symbol')
            ->get();
    }

    public function wallets(Asset $asset): Collection
    {
        return $asset->wallets()->orderByDesc('active')->orderBy('id')->get([
            'id',
            'asset_id',
            'network',
            'wallet_address',
            'active',
            'created_at',
            'updated_at',
        ]);
    }

    public function updateAssetStatus(Asset $asset, User $actor, bool $active): Asset
    {
        Gate::forUser($actor)->authorize('admin');

        return DB::transaction(function () use ($asset, $actor, $active): Asset {
            $asset = Asset::query()->lockForUpdate()->findOrFail($asset->getKey());
            $oldValues = ['active' => $asset->active];
            $asset->active = $active;
            $asset->save();

            if ($oldValues['active'] !== $asset->active) {
                app(AuditLogService::class)->record(
                    $actor,
                    'asset.status.updated',
                    entity: $asset,
                    oldValues: $oldValues,
                    newValues: ['active' => $asset->active],
                );
            }

            return $asset->refresh();
        }, 3);
    }

    public function createWallet(Asset $asset, User $actor, string $walletAddress, ?string $network, bool $active): AssetWallet
    {
        Gate::forUser($actor)->authorize('admin');

        return DB::transaction(function () use ($asset, $actor, $walletAddress, $network, $active): AssetWallet {
            $asset = Asset::query()->lockForUpdate()->findOrFail($asset->getKey());
            $walletAddress = trim($walletAddress);

            if (AssetWallet::query()->where('asset_id', $asset->getKey())->where('wallet_address', $walletAddress)->exists()) {
                throw ValidationException::withMessages([
                    'wallet_address' => ['This wallet address is already configured for the asset.'],
                ]);
            }

            if ($active) {
                $this->deactivateOtherActiveWallets($asset, $actor);
            }
            $wallet = new AssetWallet;
            $wallet->asset()->associate($asset);
            $wallet->network = $network;
            $wallet->wallet_address = $walletAddress;
            $wallet->active = $active;
            $wallet->save();
            app(AuditLogService::class)->record(
                $actor,
                'asset_wallet.created',
                entity: $wallet,
                oldValues: null,
                newValues: [
                    'asset_id' => $asset->getKey(),
                    'network' => $wallet->network,
                    'wallet_address' => $wallet->wallet_address,
                    'active' => $wallet->active,
                ],
            );

            return $wallet->refresh();
        }, 3);
    }

    public function updateWalletStatus(AssetWallet $wallet, User $actor, bool $active): AssetWallet
    {
        Gate::forUser($actor)->authorize('admin');

        return DB::transaction(function () use ($wallet, $actor, $active): AssetWallet {
            $wallet = AssetWallet::query()->lockForUpdate()->findOrFail($wallet->getKey());
            $asset = Asset::query()->lockForUpdate()->findOrFail($wallet->asset_id);

            if ($wallet->active !== $active) {
                if ($active) {
                    $this->deactivateOtherActiveWallets($asset, $actor, $wallet);
                } else {
                    $this->assertCanDeactivateWallet($asset, $wallet);
                }
                $oldValues = [
                    'asset_id' => $asset->getKey(),
                    'active' => $wallet->active,
                ];
                $wallet->active = $active;
                $wallet->save();
                app(AuditLogService::class)->record(
                    $actor,
                    'asset_wallet.status.updated',
                    entity: $wallet,
                    oldValues: $oldValues,
                    newValues: [
                        'asset_id' => $asset->getKey(),
                        'active' => $wallet->active,
                    ],
                );
            }

            return $wallet->refresh();
        }, 3);
    }

    private function assertCanDeactivateWallet(Asset $asset, AssetWallet $wallet): void
    {
        $activeWallets = $asset->wallets()->where('active', true)->count();

        if ($activeWallets <= 1 && $wallet->active) {
            throw ValidationException::withMessages([
                'active' => ['An asset must retain one active wallet configuration for deposits.'],
            ]);
        }
    }

    private function deactivateOtherActiveWallets(Asset $asset, User $actor, ?AssetWallet $except = null): void
    {
        $activeWallets = $asset->wallets()
            ->where('active', true)
            ->when($except, fn ($query) => $query->where('id', '!=', $except->getKey()))
            ->lockForUpdate()
            ->get();

        foreach ($activeWallets as $activeWallet) {
            $activeWallet->active = false;
            $activeWallet->save();
            app(AuditLogService::class)->record(
                $actor,
                'asset_wallet.status.updated',
                entity: $activeWallet,
                oldValues: ['asset_id' => $asset->getKey(), 'active' => true],
                newValues: ['asset_id' => $asset->getKey(), 'active' => false],
                metadata: ['reason' => 'replaced_by_active_wallet'],
            );
        }
    }
}
