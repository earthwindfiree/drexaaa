<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(StrategyTierSeeder::class);
        $this->call(AssetSeeder::class);
        $this->call(MarketPriceSeeder::class);
        $this->call(DemoDataSeeder::class);
    }
}
