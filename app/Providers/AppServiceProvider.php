<?php

namespace App\Providers;

use App\Audit\AuditActor;
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
        $this->app->scoped(AuditActor::class, fn (): AuditActor => new AuditActor);

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

        // A verificação de documento é pública: limitar sempre pelo IP, mesmo com sessão autenticada.
        RateLimiter::for('astuti-document-verification', fn (Request $request) => Limit::perMinute(20)->by(
            hash('sha256', 'document-verification|'.(string) $request->ip()),
        ));

        // Dois limites somados: por conta e IP, e por conta em qualquer IP.
        // O segundo impede contornar o primeiro trocando de endereço.
        RateLimiter::for('astuti-second-factor', fn (Request $request) => [
            Limit::perMinute(5)->by(
                hash('sha256', (string) $request->user()?->getAuthIdentifier().'|'.(string) $request->ip()),
            ),
            Limit::perMinute(10)->by(
                hash('sha256', 'conta|'.(string) $request->user()?->getAuthIdentifier()),
            ),
        ]);
    }
}
