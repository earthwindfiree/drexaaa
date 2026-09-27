<?php

namespace App\Services;

use App\Contracts\AssetPriceProvider;
use App\Models\Asset;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class StoredAssetPriceProvider implements AssetPriceProvider
{
    public function __construct(private readonly MarketPriceService $marketPriceService) {}

    public function currentUsdPriceFor(Asset $asset): string
    {
        try {
            return $this->marketPriceService->currentFor($asset)->current_price;
        } catch (ModelNotFoundException $exception) {
            throw new ModelNotFoundException("No simulated USD price is configured for asset [{$asset->symbol}].", 0, $exception);
        }
    }
}
