<?php

namespace App\Providers;

use App\Contracts\AssetPriceProvider;
use App\Models\User;
use App\Services\StoredAssetPriceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
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
        Gate::define('super-admin', static fn (User $user): bool => $user->status === 'active' && $user->role === 'super_admin');

        ResetPassword::createUrlUsing(static function (User $user, string $token): string {
            $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
            $query = http_build_query([
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);

            return $frontendUrl.'/reset-password?'.$query;
        });
    }
}
