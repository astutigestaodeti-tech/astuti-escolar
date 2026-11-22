<?php

namespace Tests\Feature\Auth;

use Tests\Support\TwoMunicipalityTestCase;

class TenantAccessTest extends TwoMunicipalityTestCase
{
    public function test_visitante_e_redirecionado_ao_login(): void
    {
        $this->get('/m/municipio-a/escolas')->assertRedirect('/login');
    }

    public function test_usuario_da_rede_a_nao_acessa_rede_b(): void
    {
        $user = $this->criarUsuario('usuario-a@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');

        $this->actingAs($user)->get('/m/municipio-a/escolas')
            ->assertOk()
            ->assertSee('Unidade A')
            ->assertSee('Segurança da conta')
            ->assertSee(route('two-factor.settings'))
            ->assertDontSee('Unidade B');

        $this->get('/m/municipio-b/escolas')->assertForbidden();
    }

    public function test_mesmo_usuario_com_vinculos_validos_acessa_a_b_a_sem_vazar_escolas(): void
    {
        $user = $this->criarUsuario('gestor-duas-redes@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');
        $this->vincular($user, $this->municipioB, 'municipal_manager');

        $this->actingAs($user)->get('/m/municipio-a/escolas')
            ->assertOk()->assertSee('Unidade A')->assertDontSee('Unidade B');

        $this->get('/m/municipio-b/escolas')
            ->assertOk()->assertSee('Unidade B')->assertDontSee('Unidade A');

        $this->get('/m/municipio-a/escolas')
            ->assertOk()->assertSee('Unidade A')->assertDontSee('Unidade B');
    }

    public function test_suporte_astuti_sem_vinculo_nao_consulta_rede(): void
    {
        $user = $this->criarUsuario('suporte@example.invalid');

        $this->actingAs($user)->get('/m/municipio-a/escolas')->assertForbidden();
    }

    public function test_municipio_desconhecido_nao_abre_conexao(): void
    {
        $user = $this->criarUsuario('gestor@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');

        $this->actingAs($user)->get('/m/municipio-inexistente/escolas')->assertNotFound();
    }

    public function test_usuario_bloqueado_nao_consulta_rede_mesmo_com_sessao(): void
    {
        $user = $this->criarUsuario('suspenso@example.invalid', 'inactive');
        $this->vincular($user, $this->municipioA, 'municipal_manager');

        $this->actingAs($user)->get('/m/municipio-a/escolas')->assertForbidden();
    }

    public function test_vinculo_inativo_nao_autoriza_acesso(): void
    {
        $user = $this->criarUsuario('ex-diretor@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager', [], 'inactive');

        $this->actingAs($user)->get('/m/municipio-a/escolas')->assertForbidden();
    }
}
