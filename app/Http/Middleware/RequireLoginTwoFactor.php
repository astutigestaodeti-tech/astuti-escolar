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

            if ($user->two_factor_confirmed_at !== null
                && ! app(TwoFactorSession::class)->isVerified($request, $user)) {
                return redirect()->guest(route('two-factor.challenge'));
            }
        }

        return $next($request);
    }
}
