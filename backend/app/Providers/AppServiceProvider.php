<?php

namespace App\Providers;

use App\Contracts\AssetPriceProvider;
use App\Services\StoredAssetPriceProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AssetPriceProvider::class, StoredAssetPriceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
