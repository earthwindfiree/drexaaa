<?php

namespace App\Contracts;

use App\Models\Asset;

interface AssetPriceProvider
{
    public function currentUsdPriceFor(Asset $asset): string;
}
