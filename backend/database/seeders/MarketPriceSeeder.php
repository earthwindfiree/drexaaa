<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\MarketPrice;
use Illuminate\Database\Seeder;

class MarketPriceSeeder extends Seeder
{
    public function run(): void
    {
        $prices = [
            'BTC' => ['67540.00000000', '3.4200'],
            'ETH' => ['3420.00000000', '2.1700'],
            'LTC' => ['92.30000000', '-0.8400'],
            'USDT' => ['1.00000000', '0.0200'],
        ];

        foreach ($prices as $symbol => [$currentPrice, $change24hPercentage]) {
            $asset = Asset::where('symbol', $symbol)->firstOrFail();
            $marketPrice = $asset->marketPrice()->first() ?? new MarketPrice;
            $marketPrice->asset()->associate($asset);
            $marketPrice->current_price = $currentPrice;
            $marketPrice->change_24h_percentage = $change24hPercentage;
            $marketPrice->save();
        }
    }
}
