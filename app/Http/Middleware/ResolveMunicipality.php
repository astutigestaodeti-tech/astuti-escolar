<?php

namespace App\Http\Middleware;

use App\Auth\SchoolAccess;
use App\Models\Platform\Municipality;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveMunicipality
{
    public function __construct(
        private readonly SchoolAccess $access,
        private readonly TenantContext $context,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $municipality = Municipality::query()
            ->where('slug', $request->route('municipio'))
            ->where('status', 'active')
            ->firstOrFail();

        $user = $request->user();

        if ($user === null || $this->access->hasMunicipalAccess($user, $municipality) === false) {
            abort(403);
        }

        $request->attributes->set('astuti.municipality', $municipality);

        return $this->context->runFor($municipality, fn () => $next($request));
    }
}
