<?php

namespace Tests\Feature;

use App\Models\Tier;
use App\Models\User;
use App\Services\TierCalculator;
use Database\Seeders\StrategyTierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TierCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_highest_qualifying_tier_is_selected_at_balance_boundaries(): void
    {
        $this->seed(StrategyTierSeeder::class);
        $calculator = new TierCalculator;
        $cases = [
            ['99.99', null],
            ['100.00', 'Foundation'],
            ['999.99', 'Foundation'],
            ['1000.00', 'Momentum'],
            ['4999.99', 'Momentum'],
            ['5000.00', 'Elevation'],
            ['24999.99', 'Elevation'],
            ['25000.00', 'Apex'],
        ];

        foreach ($cases as [$balance, $expectedTier]) {
            $this->assertSame($expectedTier, $calculator->qualifyingTier($balance)?->name);
        }
    }

    public function test_seeder_creates_exactly_four_matching_strategies_and_tiers(): void
    {
        $this->seed(StrategyTierSeeder::class);
        $this->seed(StrategyTierSeeder::class);

        $this->assertDatabaseCount('strategies', 4);
        $this->assertDatabaseCount('tiers', 4);
        $this->assertDatabaseHas('tiers', [
            'name' => 'Apex',
            'minimum_balance' => '25000.00',
        ]);
        $this->assertDatabaseHas('strategies', [
            'name' => 'Apex',
            'risk_profile' => 'high',
        ]);
    }

    public function test_account_tier_strategy_relationships_are_connected(): void
    {
        $this->seed(StrategyTierSeeder::class);
        $user = User::factory()->create();
        $tier = Tier::where('name', 'Foundation')->firstOrFail();
        $account = $user->account()->create([]);

        $account->tier()->associate($tier);
        $account->save();

        $this->assertTrue($account->refresh()->user->is($user));
        $this->assertTrue($account->tier->is($tier));
        $this->assertTrue($tier->strategy->tiers->contains('id', $tier->id));
    }
}
