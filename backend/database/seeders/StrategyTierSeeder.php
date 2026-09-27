<?php

namespace Database\Seeders;

use App\Models\Strategy;
use App\Models\Tier;
use Illuminate\Database\Seeder;

class StrategyTierSeeder extends Seeder
{
    public function run(): void
    {
        $profiles = [
            'Foundation' => [
                'minimum_balance' => '100.00',
                'risk_profile' => 'conservative',
                'description' => 'Entry-level profile with measured exposure, diversification, capital preservation emphasis, and steady monitoring.',
                'benefits' => ['Measured exposure', 'Diversification', 'Capital preservation emphasis', 'Steady monitoring'],
            ],
            'Momentum' => [
                'minimum_balance' => '1000.00',
                'risk_profile' => 'moderate',
                'description' => 'Balanced profile focused on market momentum, diversified exposure, active monitoring, and tactical allocation.',
                'benefits' => ['Market momentum', 'Diversified exposure', 'Active monitoring', 'Tactical allocation'],
            ],
            'Elevation' => [
                'minimum_balance' => '5000.00',
                'risk_profile' => 'moderate-high',
                'description' => 'Advanced profile with dynamic allocation, broader opportunities, and more active management.',
                'benefits' => ['Dynamic allocation', 'Broader opportunities', 'More active management'],
            ],
            'Apex' => [
                'minimum_balance' => '25000.00',
                'risk_profile' => 'high',
                'description' => 'Premium profile with dynamic positioning, broader market exposure, and comprehensive portfolio analysis.',
                'benefits' => ['Dynamic positioning', 'Broader market exposure', 'Comprehensive portfolio analysis'],
            ],
        ];

        foreach ($profiles as $name => $profile) {
            $strategy = Strategy::updateOrCreate(
                ['name' => $name],
                [
                    'description' => $profile['description'],
                    'risk_profile' => $profile['risk_profile'],
                    'active' => true,
                ],
            );

            Tier::updateOrCreate(
                ['name' => $name],
                [
                    'minimum_balance' => $profile['minimum_balance'],
                    'strategy_id' => $strategy->id,
                    'description' => $profile['description'],
                    'benefits' => $profile['benefits'],
                    'feature_access' => [],
                    'display_settings' => [],
                ],
            );
        }
    }
}
