<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    private const CSP = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:{TILES}; font-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'";

    /** Só o servidor de mapas configurado pode entregar imagens além do próprio sistema. */
    private static function csp(): string
    {
        $host = parse_url((string) config('geo.tiles_url'), PHP_URL_HOST);
        $scheme = parse_url((string) config('geo.tiles_url'), PHP_URL_SCHEME);

        return str_replace('{TILES}', is_string($host) && $host !== '' && in_array($scheme, ['https', 'http'], true) ? ' '.$scheme.'://'.$host : '', self::CSP);
    }

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', self::csp());
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if (app()->environment('production') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        } else {
            $response->headers->remove('Strict-Transport-Security');
        }

        return $response;
    }
}
