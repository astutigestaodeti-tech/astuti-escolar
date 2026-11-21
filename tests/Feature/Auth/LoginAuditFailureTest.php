<?php

namespace Tests\Feature\Auth;

use App\Audit\AuditTrail;
use Mockery;
use RuntimeException;
use Tests\Support\TwoMunicipalityTestCase;

class LoginAuditFailureTest extends TwoMunicipalityTestCase
{
    public function test_login_nao_concede_sessao_se_a_auditoria_falhar(): void
    {
        $user = $this->criarUsuario('auditoraindisponivel@example.invalid');
        $mock = Mockery::mock(AuditTrail::class);
        $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('Falha de auditoria simulada'));
        app()->instance(AuditTrail::class, $mock);

        $this->post('/login', ['email' => $user->email, 'password' => 'SenhaForteDeTeste123!'])
            ->assertStatus(503);
        $this->assertGuest();
    }

    public function test_logout_encerra_sessao_mesmo_se_a_auditoria_falhar(): void
    {
        $user = $this->criarUsuario('auditoriafalhoulogout@example.invalid');
        $this->actingAs($user);
        $mock = Mockery::mock(AuditTrail::class);
        $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('Falha de auditoria simulada'));
        app()->instance(AuditTrail::class, $mock);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_i6_senha_incorreta_com_auditoria_indisponivel_retorna_503_sem_autenticar(): void
    {
        $user = $this->criarUsuario('auditoraindisponivelfalha@astuti.invalid');
        $mock = Mockery::mock(AuditTrail::class);
        $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('Falha de auditoria simulada'));
        app()->instance(AuditTrail::class, $mock);

        $this->post('/login', ['email' => $user->email, 'password' => 'SenhaIncorretaDeTeste123!'])
            ->assertStatus(503);
        $this->assertGuest();
    }
}
