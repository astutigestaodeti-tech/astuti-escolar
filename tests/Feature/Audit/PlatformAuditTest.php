<?php

namespace Tests\Feature\Audit;

use App\Models\Platform\PlatformAuditLog;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\TwoMunicipalityTestCase;

class PlatformAuditTest extends TwoMunicipalityTestCase
{
    public function test_login_correto_falho_e_logout_sao_auditados_sem_senha_ou_token(): void
    {
        $user = $this->criarUsuario('acesso-auditado@example.invalid');

        $this->post('/login', ['email' => $user->email, 'password' => 'errada'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'SenhaForteDeTeste123!'])->assertRedirect('/');
        $this->post('/logout')->assertRedirect('/login');

        $logs = PlatformAuditLog::query()->whereIn('action', ['auth.failed', 'auth.login', 'auth.logout'])->orderBy('occurred_at')->get();
        $this->assertSame(['auth.failed', 'auth.login', 'auth.logout'], $logs->pluck('action')->all());
        $this->assertSame($user->id, $logs[1]->user_id);
        $this->assertSame($user->id, $logs[2]->user_id);
        $this->assertStringNotContainsString('SenhaForteDeTeste123!', $logs->toJson());
        $this->assertStringNotContainsString('errada', $logs->toJson());
        $this->assertStringNotContainsString('token', $logs->toJson());
    }

    public function test_provisionamento_registra_resultado_na_plataforma(): void
    {
        $this->municipioA->update(['status' => 'pending']);

        $this->assertSame(0, Artisan::call('astuti:municipio-provisionar', ['slug' => 'municipio-a']));

        $log = PlatformAuditLog::query()
            ->where('action', 'municipality.provision.completed')
            ->where('auditable_id', $this->municipioA->id)->firstOrFail();

        $this->assertSame('completed', $log->after['status']);
    }
}
