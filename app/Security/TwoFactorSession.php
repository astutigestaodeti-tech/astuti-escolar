<?php

namespace App\Security;

use App\Models\User;
use Illuminate\Http\Request;

class TwoFactorSession
{
    private const SESSION_KEY = 'astuti.two_factor';

    private const MAX_AGE_SECONDS = 900;

    public function markVerified(Request $request, User $user): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $user->getKey(),
            'secret_fingerprint' => hash('sha256', (string) $user->two_factor_secret),
            'verified_at' => now()->getTimestamp(),
        ]);
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    public function isVerified(Request $request, User $user): bool
    {
        if ($user->two_factor_confirmed_at === null || ! is_string($user->two_factor_secret)) {
            return false;
        }

        $state = $request->session()->get(self::SESSION_KEY);
        if (! is_array($state) || ($state['user_id'] ?? null) !== $user->getKey()) {
            return false;
        }

        $verifiedAt = $state['verified_at'] ?? null;
        $now = now()->getTimestamp();

        return is_int($verifiedAt)
            && $verifiedAt <= $now
            && $now - $verifiedAt <= self::MAX_AGE_SECONDS
            && is_string($state['secret_fingerprint'] ?? null)
            && hash_equals(hash('sha256', $user->two_factor_secret), $state['secret_fingerprint']);
    }
}
