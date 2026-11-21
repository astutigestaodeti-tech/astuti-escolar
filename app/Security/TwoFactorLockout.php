<?php

namespace App\Security;

use App\Audit\AuditTrail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Bloqueio do segundo fator por conta, e nao por IP.
 *
 * Quem ja conhece a senha pode trocar de endereco para contornar o limite
 * por IP; por isso os erros seguidos ficam gravados na propria conta.
 */
class TwoFactorLockout
{
    public const MAX_CONSECUTIVE_FAILURES = 10;

    public const LOCK_MINUTES = 15;

    /** Recusa qualquer codigo, mesmo correto, enquanto a conta estiver bloqueada. */
    public function assertNotLocked(User $user): void
    {
        $until = $user->two_factor_locked_until;

        if ($until !== null && $until->isFuture()) {
            throw ValidationException::withMessages([
                'code' => 'Muitas tentativas incorretas. Por segurança, o segundo fator está bloqueado até '
                    .$until->timezone(config('app.timezone'))->format('H:i').'.',
            ]);
        }
    }

    /**
     * Registra o erro em transacao propria: a transacao do desafio e desfeita
     * quando o codigo e recusado, e a contagem nao pode ser desfeita junto.
     */
    public function registerFailure(string $userId): void
    {
        DB::connection('platform')->transaction(function () use ($userId): void {
            $locked = User::query()->lockForUpdate()->findOrFail($userId);
            $failures = (int) $locked->two_factor_failed_attempts + 1;

            app(AuditTrail::class)->record('platform', 'auth.two_factor_failed', User::class, $userId, userId: $userId);

            if ($failures >= self::MAX_CONSECUTIVE_FAILURES) {
                $locked->forceFill([
                    'two_factor_failed_attempts' => 0,
                    'two_factor_locked_until' => now()->addMinutes(self::LOCK_MINUTES),
                ])->save();
                app(AuditTrail::class)->record('platform', 'auth.two_factor_locked', User::class, $userId, userId: $userId);

                return;
            }

            $locked->forceFill(['two_factor_failed_attempts' => $failures])->save();
        });
    }

    /** Valores a gravar junto com um desafio aceito. */
    public function resetValues(): array
    {
        return ['two_factor_failed_attempts' => 0, 'two_factor_locked_until' => null];
    }
}
