<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MarketHistoryPoint;
use Database\Seeders\AssetSeeder;
use Database\Seeders\MarketPriceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AssetSeeder::class);
        $this->seed(MarketPriceSeeder::class);
    }

    public function test_market_history_is_public_and_returns_configured_asset_and_ordered_points(): void
    {
        $btc = Asset::query()->where('symbol', 'BTC')->firstOrFail();

        $this->getJson('/api/markets/'.$btc->id.'/history')
            ->assertOk()
            ->assertJsonPath('data.asset.symbol', 'BTC')
            ->assertJsonPath('data.asset.current_price', '67540.00000000')
            ->assertJsonPath('data.range', '30d')
            ->assertJsonCount(181, 'data.points');

        $points = MarketHistoryPoint::query()
            ->where('asset_id', $btc->id)
            ->orderBy('recorded_at')
            ->get();
        $this->assertSame(
            $points->last()->recorded_at->toISOString(),
            $this->getJson('/api/markets/'.$btc->id.'/history')->json('data.points.180.timestamp'),
        );
        $this->assertSame('67540.00000000', $points->last()->price);
    }

    public function test_history_range_and_limit_are_bounded_and_return_ascending_points(): void
    {
        $eth = Asset::query()->where('symbol', 'ETH')->firstOrFail();

        $response = $this->getJson('/api/markets/'.$eth->id.'/history?range=7d&limit=3')
            ->assertOk()
            ->assertJsonPath('data.range', '7d')
            ->assertJsonCount(3, 'data.points');
        $timestamps = array_column($response->json('data.points'), 'timestamp');

        $this->assertSame($timestamps, collect($timestamps)->sort()->values()->all());
        $this->getJson('/api/markets/'.$eth->id.'/history?range=24h&limit=201')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('limit');
        $this->getJson('/api/markets/'.$eth->id.'/history?range=all')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('range');
    }

    public function test_24_hour_history_is_public_and_returns_valid_ordered_points(): void
    {
        $btc = Asset::query()->where('symbol', 'BTC')->firstOrFail();

        $response = $this->getJson('/api/markets/'.$btc->id.'/history?range=24h')
            ->assertOk()
            ->assertJsonPath('data.asset.id', $btc->id)
            ->assertJsonPath('data.range', '24h')
            ->assertJsonStructure([
                'data' => [
                    'points' => [
                        '*' => ['timestamp', 'price'],
                    ],
                ],
            ]);
        $timestamps = array_column($response->json('data.points'), 'timestamp');

        $this->assertNotEmpty($timestamps);
        $this->assertSame($timestamps, collect($timestamps)->sort()->values()->all());
    }

    public function test_inactive_and_unknown_assets_are_not_available_publicly(): void
    {
        $ltc = Asset::query()->where('symbol', 'LTC')->firstOrFail();
        $ltc->update(['active' => false]);

        $this->getJson('/api/markets/'.$ltc->id.'/history')->assertNotFound();
        $this->getJson('/api/markets/999999/history')->assertNotFound();
        $this->getJson('/api/markets/not-a-number/history')->assertNotFound();
    }

    public function test_admin_price_updates_record_a_new_historical_point(): void
    {
        $btc = Asset::query()->where('symbol', 'BTC')->firstOrFail();
        $admin = \App\Models\User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)->patchJson('/api/admin/markets/'.$btc->id.'/price', [
            'current_price' => '68000',
            'change_24h_percentage' => '4.0000',
        ])->assertOk();

        $this->assertDatabaseHas('market_history_points', [
            'asset_id' => $btc->id,
            'price' => '68000.00000000',
        ]);
    }
}
