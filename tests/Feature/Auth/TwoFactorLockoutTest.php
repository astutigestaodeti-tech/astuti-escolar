<?php

namespace Tests\Feature\Auth;

use App\Models\Platform\PlatformAuditLog;
use App\Models\User;
use App\Security\Totp;
use App\Security\TwoFactorLockout;
use Tests\Support\TwoMunicipalityTestCase;

class TwoFactorLockoutTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private function contaComSegundoFator(string $email): User
    {
        $user = $this->criarUsuario($email);
        $user->forceFill([
            'two_factor_secret' => self::SECRET,
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user;
    }

    private function tentar(string $code, string $ip)
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post('/seguranca/segundo-fator/desafio', ['code' => $code]);
    }

    private function codigoValido(): string
    {
        return app(Totp::class)->at(self::SECRET, now()->getTimestamp());
    }

    public function test_limite_por_conta_vale_mesmo_trocando_de_ip(): void
    {
        $user = $this->contaComSegundoFator('limite-conta@example.invalid');
        $this->actingAs($user);

        // Dez tentativas em cinco IPs diferentes passam pelo limite por IP (5 por minuto)...
        for ($i = 0; $i < 10; $i++) {
            $this->tentar(sprintf('%06d', 100000 + $i), '10.0.0.'.intdiv($i, 2))->assertStatus(302);
        }

        // ...mas a 11a no mesmo minuto, de um IP novo, cai no limite da conta.
        $this->tentar('123456', '10.0.9.9')->assertStatus(429);
    }

    public function test_dez_erros_seguidos_bloqueiam_a_conta_ate_o_codigo_correto(): void
    {
        $user = $this->contaComSegundoFator('bloqueio@example.invalid');
        $this->actingAs($user);

        for ($i = 0; $i < TwoFactorLockout::MAX_CONSECUTIVE_FAILURES; $i++) {
            $this->tentar(sprintf('%06d', 200000 + $i), '10.1.0.'.$i)->assertSessionHasErrors('code');
        }

        $this->assertNotNull($user->fresh()->two_factor_locked_until);

        // Novo minuto: os limites de requisição liberam, mas o bloqueio da conta continua.
        $this->travel(2)->minutes();
        $this->tentar($this->codigoValido(), '10.1.1.1')->assertSessionHasErrors('code');
        $this->assertNull(session('astuti.two_factor'));

        $this->assertSame(10, PlatformAuditLog::query()->where('action', 'auth.two_factor_failed')->where('user_id', $user->id)->count());
        $this->assertSame(1, PlatformAuditLog::query()->where('action', 'auth.two_factor_locked')->where('user_id', $user->id)->count());

        // Passado o bloqueio, o código correto volta a funcionar e zera a contagem.
        $this->travel(TwoFactorLockout::LOCK_MINUTES)->minutes();
        $this->tentar($this->codigoValido(), '10.1.1.1')->assertRedirect('/');

        $fresh = $user->fresh();
        $this->assertSame(0, $fresh->two_factor_failed_attempts);
        $this->assertNull($fresh->two_factor_locked_until);
    }

    public function test_acerto_zera_erros_anteriores(): void
    {
        $user = $this->contaComSegundoFator('zera@example.invalid');
        $this->actingAs($user);

        for ($i = 0; $i < 3; $i++) {
            $this->tentar(sprintf('%06d', 300000 + $i), '10.2.0.1')->assertSessionHasErrors('code');
        }
        $this->assertSame(3, $user->fresh()->two_factor_failed_attempts);

        $this->tentar($this->codigoValido(), '10.2.0.1')->assertRedirect('/');
        $this->assertSame(0, $user->fresh()->two_factor_failed_attempts);
    }

    public function test_auditoria_do_erro_nao_guarda_o_codigo_digitado(): void
    {
        $user = $this->contaComSegundoFator('sem-codigo@example.invalid');
        $this->actingAs($user);

        $this->tentar('987654', '10.3.0.1')->assertSessionHasErrors('code');

        $log = PlatformAuditLog::query()->where('action', 'auth.two_factor_failed')->firstOrFail();
        $this->assertSame($user->id, $log->user_id);
        $this->assertStringNotContainsString('987654', json_encode($log->toArray()));
    }
}
