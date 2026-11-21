<?php

namespace App\Http\Middleware;

use App\Security\TwoFactorSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireConfirmedTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! app(TwoFactorSession::class)->isVerified($request, $user->fresh())) {
            abort(403, 'Confirme o segundo fator de autenticação antes de alterar registros escolares.');
        }

        return $next($request);
    }
}
