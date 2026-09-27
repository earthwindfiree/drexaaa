<?php

namespace Tests\Feature;

use App\Models\Tier;
use App\Models\User;
use App\Services\AccountBalanceService;
use Database\Seeders\StrategyTierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountBalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_managed_balance_changes_synchronize_balance_and_qualifying_tier(): void
    {
        $this->seed(StrategyTierSeeder::class);
        $account = User::factory()->create()->account()->create([]);
        $service = app(AccountBalanceService::class);
        $cases = [
            ['0', '0.00', null],
            ['99.99', '99.99', null],
            ['100', '100.00', 'Foundation'],
            ['1000', '1000.00', 'Momentum'],
            ['5000', '5000.00', 'Elevation'],
            ['25000', '25000.00', 'Apex'],
            ['25000.01', '25000.01', 'Apex'],
        ];

        foreach ($cases as [$balance, $expectedBalance, $expectedTier]) {
            $account = $service->setManagedBalance($account, $balance);

            $this->assertSame($expectedBalance, $account->managed_balance);
            $this->assertSame(
                $expectedTier === null ? null : Tier::where('name', $expectedTier)->value('id'),
                $account->tier_id,
            );
        }

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'managed_balance' => '25000.01',
            'tier_id' => Tier::where('name', 'Apex')->value('id'),
        ]);
    }
}
