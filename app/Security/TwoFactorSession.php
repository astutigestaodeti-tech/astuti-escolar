<?php

namespace App\Security;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Estado do segundo fator na sessão.
 *
 * Há dois níveis:
 * - verificação de entrada: o código é pedido uma vez ao entrar e vale enquanto a
 *   sessão estiver viva (a sessão termina após 2 horas sem uso), com teto de
 *   12 horas para quem deixa o sistema aberto o dia inteiro;
 * - confirmação recente: ações delicadas (excluir cadastro, desativar o segundo
 *   fator) exigem que o código tenha sido digitado nos últimos 15 minutos.
 */
class TwoFactorSession
{
    private const SESSION_KEY = 'astuti.two_factor';

    private const PENDING_INPUT_KEY = 'astuti.two_factor.pending_input';

    private const REASON_KEY = 'astuti.two_factor.reason';

    public const LOGIN_MAX_AGE_SECONDS = 43200;

    public const RECENT_MAX_AGE_SECONDS = 900;

    private const PENDING_INPUT_MAX_BYTES = 32768;

    /** Campos que nunca são guardados para reapresentar o formulário. */
    private const NEVER_KEEP = ['_token', '_method', 'password', 'password_confirmation', 'code', 'token', 'arquivo'];

    public function markVerified(Request $request, User $user): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $user->getKey(),
            'secret_fingerprint' => hash('sha256', (string) $user->two_factor_secret),
            'verified_at' => now()->getTimestamp(),
        ]);
        $request->session()->forget(self::REASON_KEY);
    }

    public function clear(Request $request): void
    {
        $request->session()->forget([self::SESSION_KEY, self::PENDING_INPUT_KEY, self::REASON_KEY, DevelopmentAdminAccess::SESSION_KEY]);
    }

    /** Verificação de entrada: vale para consultar e gravar no dia a dia. */
    public function isVerified(Request $request, User $user): bool
    {
        return app(DevelopmentAdminAccess::class)->active($request, $user)
            || $this->verifiedWithin($request, $user, self::LOGIN_MAX_AGE_SECONDS);
    }

    /** Console técnico: exige confirmação real, sem a exceção de desenvolvimento. */
    public function isVerifiedWithoutBypass(Request $request, User $user): bool
    {
        return $request->hasSession()
            && $user->status === 'active'
            && is_string($user->two_factor_secret)
            && $user->two_factor_secret !== ''
            && $this->verifiedWithin($request, $user, self::LOGIN_MAX_AGE_SECONDS);
    }

    /** Confirmação recente: exigida antes de ações delicadas. */
    public function isRecentlyVerified(Request $request, User $user): bool
    {
        return app(DevelopmentAdminAccess::class)->active($request, $user)
            || $this->verifiedWithin($request, $user, self::RECENT_MAX_AGE_SECONDS);
    }

    /**
     * Prepara o desvio para a tela do código sem perder o trabalho do usuário.
     *
     * Em uma consulta (GET), volta para a própria página. Em uma gravação, volta
     * para a página do formulário e guarda o que foi digitado, para reapresentar
     * os campos preenchidos depois da confirmação. Arquivos, senhas e códigos
     * nunca são guardados.
     */
    public function rememberDestination(Request $request, ?string $reason = null): void
    {
        $session = $request->session();

        if ($reason !== null) {
            $session->put(self::REASON_KEY, $reason);
        }

        if ($request->isMethod('GET')) {
            $session->put('url.intended', $this->relativeUrl($request->path(), $request->getQueryString()));

            return;
        }

        if (! in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        $previous = $session->previousUrl();
        if (is_string($previous) && $previous !== '') {
            $parts = parse_url($previous);
            if (is_array($parts) && ($parts['host'] ?? $request->getHost()) === $request->getHost()) {
                $session->put('url.intended', $this->relativeUrl($parts['path'] ?? '/', $parts['query'] ?? null));
            }
        }

        $input = $this->keepableInput($request->except(self::NEVER_KEEP));
        if ($input !== [] && strlen(serialize($input)) <= self::PENDING_INPUT_MAX_BYTES) {
            $session->put(self::PENDING_INPUT_KEY, $input);
        } else {
            $session->forget(self::PENDING_INPUT_KEY);
        }
    }

    /** Devolve (e apaga) o que foi digitado antes do desvio, se houver. */
    public function pullPendingInput(Request $request): array
    {
        $input = $request->session()->pull(self::PENDING_INPUT_KEY);

        return is_array($input) ? $input : [];
    }

    public function reason(Request $request): ?string
    {
        $reason = $request->session()->get(self::REASON_KEY);

        return is_string($reason) ? $reason : null;
    }

    private function verifiedWithin(Request $request, User $user, int $maxAge): bool
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
            && $now - $verifiedAt <= $maxAge
            && is_string($state['secret_fingerprint'] ?? null)
            && hash_equals(hash('sha256', $user->two_factor_secret), $state['secret_fingerprint']);
    }

    private function relativeUrl(string $path, ?string $query): string
    {
        $path = '/'.ltrim($path, '/');

        return ($query !== null && $query !== '') ? $path.'?'.$query : $path;
    }

    private function keepableInput(array $input): array
    {
        $kept = [];
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $value = $this->keepableInput($value);
                if ($value !== []) {
                    $kept[$key] = $value;
                }
            } elseif (is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
                $kept[$key] = $value;
            }
        }

        return $kept;
    }
}
