<?php

namespace Tests\Feature;

use App\Models\Strategy;
use App\Models\Tier;
use Database\Seeders\StrategyTierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicStrategiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StrategyTierSeeder::class);
    }

    public function test_strategy_list_is_public_and_uses_current_database_configuration(): void
    {
        $foundation = Tier::query()->where('name', 'Foundation')->firstOrFail();
        $foundation->update([
            'minimum_balance' => '125.00',
            'benefits' => ['Configured benefit'],
            'feature_access' => ['Portfolio insights'],
        ]);

        $response = $this->getJson('/api/strategies')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.name', 'Apex')
            ->assertJsonPath('data.3.name', 'Momentum');

        foreach ($response->json('data') as $strategy) {
            $this->getJson('/api/strategies/'.$strategy['id'])
                ->assertOk()
                ->assertJsonPath('data.id', $strategy['id'])
                ->assertJsonPath('data.name', $strategy['name']);
        }

        $this->getJson('/api/strategies/'.$foundation->strategy_id)
            ->assertOk()
            ->assertJsonPath('data.name', 'Foundation')
            ->assertJsonPath('data.risk_profile', 'conservative')
            ->assertJsonPath('data.tiers.0.minimum_balance', '125.00')
            ->assertJsonPath('data.tiers.0.benefits.0', 'Configured benefit')
            ->assertJsonPath('data.tiers.0.features.0', 'Portfolio insights');
    }

    public function test_inactive_or_unassigned_strategies_are_not_public_and_unknown_ids_return_not_found(): void
    {
        $inactive = Strategy::query()->where('name', 'Apex')->firstOrFail();
        $inactive->update(['active' => false]);

        $unassigned = Strategy::query()->create([
            'name' => 'Unassigned profile',
            'description' => 'No tier uses this profile.',
            'risk_profile' => 'moderate',
            'active' => true,
        ]);

        $this->getJson('/api/strategies')
            ->assertOk()
            ->assertJsonCount(3, 'data');
        $this->getJson('/api/strategies/'.$inactive->id)->assertNotFound();
        $this->getJson('/api/strategies/'.$unassigned->id)->assertNotFound();
        $this->getJson('/api/strategies/999999')->assertNotFound();
        $this->getJson('/api/strategies/not-a-number')->assertNotFound();
    }
}
