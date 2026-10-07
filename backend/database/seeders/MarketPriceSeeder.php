<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\MarketHistoryPoint;
use App\Models\MarketPrice;
use App\Support\FixedDecimalMath;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

            $asset->marketHistoryPoints()->delete();
            $records = [];
            $recordedAt = now()->startOfHour()->subDays(30);

            for ($index = 0; $index <= 180; $index++) {
                $factor = $this->priceFactor($index);
                $records[] = [
                    'asset_id' => $asset->id,
                    'price' => FixedDecimalMath::multiplyToScale($currentPrice, 8, $factor, 4, 8, 12),
                    'recorded_at' => $recordedAt->copy()->addHours($index * 4),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table((new MarketHistoryPoint)->getTable())->insert($records);
        }
    }

    private function priceFactor(int $index): string
    {
        $offsets = [0, 120, 35, 205, 125, 350, 255, 500];
        $scaledIndex = $index * (count($offsets) - 1);
        $segment = min(intdiv($scaledIndex, 180), count($offsets) - 2);
        $remainder = $scaledIndex - ($segment * 180);
        $offset = $offsets[$segment] + intdiv(($offsets[$segment + 1] - $offsets[$segment]) * $remainder, 180);
        $basisPoints = 9500 + $offset;

        return intdiv($basisPoints, 10000).'.'.str_pad((string) ($basisPoints % 10000), 4, '0', STR_PAD_LEFT);
    }
}
