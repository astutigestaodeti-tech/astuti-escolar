<?php

namespace App\Http\Middleware;

use App\Security\TwoFactorSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireLoginTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $user = $user->fresh();

            if ($user === null) {
                abort(403);
            }

            if ($user->two_factor_confirmed_at !== null
                && ! app(TwoFactorSession::class)->isVerified($request, $user)) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Confirme o segundo fator antes de consultar os dados.',
                    ], 403);
                }

                app(TwoFactorSession::class)->rememberDestination($request);

                return redirect()->route('two-factor.challenge');
            }
        }

        return $next($request);
    }
}
