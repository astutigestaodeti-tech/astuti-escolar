<?php

namespace App\Providers;

use App\Tenancy\TenantConnectionManager;
use App\Tenancy\TenantContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class, function ($app): TenantContext {
            return new TenantContext($app->make(TenantConnectionManager::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
