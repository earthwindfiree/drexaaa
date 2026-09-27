<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetWallet;
use Database\Seeders\AssetSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetWalletTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_exactly_the_four_supported_assets(): void
    {
        $this->seed(AssetSeeder::class);
        $this->seed(AssetSeeder::class);

        $this->assertDatabaseCount('assets', 4);
        $this->assertDatabaseCount('asset_wallets', 4);
        $this->assertSame(
            ['BTC', 'ETH', 'LTC', 'USDT'],
            Asset::query()->orderBy('symbol')->pluck('symbol')->all(),
        );
        $this->assertSame(
            ['Bitcoin', 'Ethereum', 'Litecoin', 'Tether'],
            Asset::query()->orderBy('symbol')->pluck('name')->all(),
        );
    }

    public function test_wallets_belong_to_their_asset_and_usdt_has_a_demo_network(): void
    {
        $this->seed(AssetSeeder::class);
        $usdt = Asset::where('symbol', 'USDT')->firstOrFail();
        $wallet = $usdt->wallets()->firstOrFail();

        $this->assertTrue($wallet->asset->is($usdt));
        $this->assertSame('Ethereum (ERC-20) - DEMO', $wallet->network);
        $this->assertStringStartsWith('DEMO-ONLY-', $wallet->wallet_address);

        foreach (['BTC', 'ETH', 'LTC'] as $symbol) {
            $asset = Asset::where('symbol', $symbol)->firstOrFail();
            $wallet = $asset->wallets()->firstOrFail();

            $this->assertTrue($wallet->asset->is($asset));
            $this->assertNull($wallet->network);
        }
    }

    public function test_assets_and_wallets_can_be_inactive(): void
    {
        $this->seed(AssetSeeder::class);
        $asset = Asset::where('symbol', 'BTC')->firstOrFail();
        $wallet = $asset->wallets()->firstOrFail();

        $asset->update(['active' => false]);
        $wallet->update(['active' => false]);

        $this->assertFalse($asset->refresh()->active);
        $this->assertFalse($wallet->refresh()->active);
    }

    public function test_same_asset_wallet_address_cannot_be_configured_twice(): void
    {
        $this->seed(AssetSeeder::class);
        $asset = Asset::where('symbol', 'BTC')->firstOrFail();
        $wallet = $asset->wallets()->firstOrFail();

        $this->expectException(QueryException::class);

        AssetWallet::create([
            'asset_id' => $asset->id,
            'network' => $wallet->network,
            'wallet_address' => $wallet->wallet_address,
            'active' => true,
        ]);
    }
}
