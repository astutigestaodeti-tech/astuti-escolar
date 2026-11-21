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

                if ($request->isMethod('GET')) {
                    $path = $request->path();
                    $destination = $path === '/' ? '/' : '/'.$path;
                    $query = $request->getQueryString();

                    if ($query !== null && $query !== '') {
                        $destination .= '?'.$query;
                    }

                    $request->session()->put('url.intended', $destination);
                }

                return redirect()->route('two-factor.challenge');
            }
        }

        return $next($request);
    }
}
