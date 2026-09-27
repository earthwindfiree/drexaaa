<?php

namespace Database\Seeders;

use App\Models\Asset;
use Illuminate\Database\Seeder;

class AssetSeeder extends Seeder
{
    public function run(): void
    {
        $assets = [
            'BTC' => [
                'name' => 'Bitcoin',
                'network' => null,
                'wallet_address' => 'DEMO-ONLY-NOT-A-VALID-BTC-WALLET',
            ],
            'ETH' => [
                'name' => 'Ethereum',
                'network' => null,
                'wallet_address' => 'DEMO-ONLY-NOT-A-VALID-ETH-WALLET',
            ],
            'LTC' => [
                'name' => 'Litecoin',
                'network' => null,
                'wallet_address' => 'DEMO-ONLY-NOT-A-VALID-LTC-WALLET',
            ],
            'USDT' => [
                'name' => 'Tether',
                'network' => 'Ethereum (ERC-20) - DEMO',
                'wallet_address' => 'DEMO-ONLY-NOT-A-VALID-USDT-WALLET',
            ],
        ];

        foreach ($assets as $symbol => $configuration) {
            $asset = Asset::updateOrCreate(
                ['symbol' => $symbol],
                ['name' => $configuration['name'], 'active' => true],
            );

            // These deliberately invalid addresses are for local development only.
            $asset->wallets()->firstOrCreate(
                ['wallet_address' => $configuration['wallet_address']],
                [
                    'network' => $configuration['network'],
                    'active' => true,
                ],
            );
        }
    }
}
