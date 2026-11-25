<?php

namespace App\Security;

use App\Models\User;
use App\Support\EmailNormalizer;
use Illuminate\Http\Request;

final class DevelopmentAdminAccess
{
    public const SESSION_KEY = 'astuti.development_admin';

    private const MAX_WINDOW_SECONDS = 604800;

    public function eligible(Request $request, User $user): bool
    {
        if (! app()->environment('local') || config('app.env') !== 'local'
            || config('auth.development_admin.enabled') !== true
            || $user->email !== EmailNormalizer::fromLogin('admin')
            || $user->status !== 'active'
            || $user->two_factor_confirmed_at === null
            || ! is_string($user->two_factor_secret)
            || $user->two_factor_secret === ''
            || ! $request->isSecure()) {
            return false;
        }

        $allowed = config('auth.development_admin.ip');
        $ip = $request->ip();
        if (! is_string($allowed) || ! is_string($ip)
            || filter_var($allowed, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false
            || $ip !== $allowed) {
            return false;
        }

        // Na VPS publicada, o Cloudflare Tunnel é o proxy confiável.
        // Não aceitar apenas X-Forwarded-For nem o endereço local do túnel.
        if ($request->isFromTrustedProxy()
            && $request->headers->get('CF-Connecting-IP') !== $ip) {
            return false;
        }

        $expires = $this->expiresAt();
        $now = now()->getTimestamp();

        return $expires > $now && $expires - $now <= self::MAX_WINDOW_SECONDS;
    }

    public function start(Request $request, User $user): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $user->getKey(),
            'expires_at' => $this->expiresAt(),
            'ip' => $request->ip(),
            'started_at' => now()->getTimestamp(),
        ]);
    }

    public function active(Request $request, User $user): bool
    {
        // Nunca dispensar o código para alterar a própria proteção da conta.
        if ($request->routeIs('two-factor.*') || ! $this->eligible($request, $user)) {
            return false;
        }

        $state = $request->session()->get(self::SESSION_KEY);
        $now = now()->getTimestamp();

        return is_array($state)
            && ($state['user_id'] ?? null) === $user->getKey()
            && ($state['expires_at'] ?? null) === $this->expiresAt()
            && ($state['ip'] ?? null) === $request->ip()
            && is_int($state['started_at'] ?? null)
            && $state['started_at'] <= $now
            && $now - $state['started_at'] <= TwoFactorSession::LOGIN_MAX_AGE_SECONDS;
    }

    private function expiresAt(): int
    {
        $value = config('auth.development_admin.expires_at');

        return is_string($value) && preg_match('/\\A[0-9]{10}\\z/D', $value) === 1 ? (int) $value : 0;
    }
}
