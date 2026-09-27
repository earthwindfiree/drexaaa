<?php

namespace App\Providers;

use App\Contracts\AssetPriceProvider;
use App\Models\User;
use App\Services\StoredAssetPriceProvider;
use Illuminate\Support\Facades\Gate;
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
        Gate::define('admin', static fn (User $user): bool => $user->status === 'active' && in_array($user->role, ['admin', 'super_admin'], true)
        );
    }
}
