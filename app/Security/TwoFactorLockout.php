<?php

namespace App\Security;

use App\Audit\AuditTrail;
use App\Models\User;
use Illuminate\Support\Carbon;
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
            throw ValidationException::withMessages(['code' => self::lockedMessage($until)]);
        }
    }

    /**
     * Registra o erro em transacao propria: a transacao do desafio e desfeita
     * quando o codigo e recusado, e a contagem nao pode ser desfeita junto.
     */
    public function registerFailure(string $userId): string
    {
        return DB::connection('platform')->transaction(function () use ($userId): string {
            $locked = User::query()->lockForUpdate()->findOrFail($userId);
            $failures = (int) $locked->two_factor_failed_attempts + 1;

            app(AuditTrail::class)->record('platform', 'auth.two_factor_failed', User::class, $userId, userId: $userId);

            if ($failures >= self::MAX_CONSECUTIVE_FAILURES) {
                $locked->forceFill([
                    'two_factor_failed_attempts' => 0,
                    'two_factor_locked_until' => now()->addMinutes(self::LOCK_MINUTES),
                ])->save();
                app(AuditTrail::class)->record('platform', 'auth.two_factor_locked', User::class, $userId, userId: $userId);

                return 'Código incorreto. '.self::lockedMessage($locked->two_factor_locked_until);
            }

            $locked->forceFill(['two_factor_failed_attempts' => $failures])->save();
            $remaining = self::MAX_CONSECUTIVE_FAILURES - $failures;

            // Avisa antes de bloquear, para a pessoa conferir o aplicativo com calma.
            return $remaining <= 3
                ? 'Código inválido, já utilizado ou expirado. '.($remaining === 1 ? 'Resta 1 tentativa' : 'Restam '.$remaining.' tentativas')
                    .' antes de o segundo fator ficar bloqueado por '.self::LOCK_MINUTES.' minutos.'
                : 'Código inválido, já utilizado ou expirado.';
        });
    }

    /** Mensagem do bloqueio, com o horário em que ele termina. */
    public static function lockedMessage(\DateTimeInterface $until): string
    {
        return 'Por segurança, depois de '.self::MAX_CONSECUTIVE_FAILURES.' tentativas erradas, o segundo fator desta conta ficou bloqueado por '
            .self::LOCK_MINUTES.' minutos, até as '.Carbon::instance($until)->timezone(config('app.timezone'))->format('H:i')
            .'. Depois desse horário, digite o código novo do aplicativo.';
    }

    /** Horário do fim do bloqueio, se a conta estiver bloqueada agora. */
    public function lockedUntil(User $user): ?\DateTimeInterface
    {
        $until = $user->two_factor_locked_until;

        return $until !== null && $until->isFuture() ? $until : null;
    }

    /** Valores a gravar junto com um desafio aceito. */
    public function resetValues(): array
    {
        return ['two_factor_failed_attempts' => 0, 'two_factor_locked_until' => null];
    }
}
