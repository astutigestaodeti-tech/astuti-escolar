<?php

namespace App\Providers;

use App\Models\Tenant\School;
use App\Policies\SchoolPolicy;
use App\Tenancy\TenantConnectionManager;
use App\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class, function ($app): TenantContext {
            return new TenantContext($app->make(TenantConnectionManager::class));
        });
    }

    public function boot(): void
    {
        Gate::policy(School::class, SchoolPolicy::class);

        RateLimiter::for('astuti-login', fn (Request $request) => Limit::perMinute(5)->by(
            hash('sha256', strtolower(trim((string) $request->input('email'))).'|'.(string) $request->ip()),
        ));
    }
}
