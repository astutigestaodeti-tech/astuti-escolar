<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;
use Tests\Support\TwoMunicipalityTestCase;

class HomeMunicipalityTest extends TwoMunicipalityTestCase
{
    public function test_a_pagina_inicial_tem_um_controlador_responsavel_pela_consulta(): void
    {
        $this->assertSame(
            HomeController::class.'@index',
            Route::getRoutes()->getByName('home')?->getActionName(),
        );
    }

    public function test_visitante_ve_a_pagina_publica_sem_redes(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Gestão escolar para redes municipais')
            ->assertDontSee('Municipio A')
            ->assertDontSee('Municipio B');
    }

    public function test_usuario_ve_so_municipio_com_vinculo_e_escopo_validos(): void
    {
        $user = $this->criarUsuario('home-autorizado@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);
        $this->vincular($user, $this->municipioB, 'school_secretary', [self::ESCOLA_B1], 'inactive');

        // Uma rede só: entra direto nela, sem a tela de escolha.
        $this->actingAs($user)->get('/')
            ->assertRedirect(route('schools.index', ['municipio' => 'municipio-a']));
    }

    public function test_membro_sem_escolas_nao_recebe_link_de_rede(): void
    {
        $user = $this->criarUsuario('home-sem-escola@example.invalid');
        $this->vincular($user, $this->municipioA, 'teacher');

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertSee('Nenhuma rede liberada')
            ->assertDontSee('Municipio A');
    }

    public function test_conta_suspensa_nao_recebe_links_de_municipios(): void
    {
        $user = $this->criarUsuario('home-inativo@example.invalid', 'inactive');
        $this->vincular($user, $this->municipioA, 'municipal_manager');

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertSee('Nenhuma rede liberada')
            ->assertDontSee('Municipio A');
    }

    public function test_municipio_inativo_nao_aparece_e_outra_rede_ativa_aparece(): void
    {
        $user = $this->criarUsuario('home-multirrede@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');
        $this->vincular($user, $this->municipioB, 'municipal_manager');
        $this->municipioA->update(['status' => 'inactive']);

        $this->actingAs($user)->get('/')
            ->assertRedirect(route('schools.index', ['municipio' => 'municipio-b']));
    }

    public function test_quem_tem_duas_redes_ativas_escolhe_na_pagina_inicial(): void
    {
        $user = $this->criarUsuario('home-duas-redes@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');
        $this->vincular($user, $this->municipioB, 'municipal_manager');

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertSee('Municipio A')
            ->assertSee('Municipio B')
            ->assertSee('Segurança da conta')
            ->assertSee(route('schools.index', ['municipio' => 'municipio-a']));
    }
}
