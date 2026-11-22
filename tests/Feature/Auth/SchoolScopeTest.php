<?php

namespace Tests\Feature\Auth;

use Tests\Support\TwoMunicipalityTestCase;

class SchoolScopeTest extends TwoMunicipalityTestCase
{
    public function test_secretaria_ve_apenas_escola_autorizada(): void
    {
        $user = $this->criarUsuario('secretaria@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);

        $this->actingAs($user)->get('/m/municipio-a/escolas')
            ->assertOk()
            ->assertSee('Unidade A')
            ->assertDontSee('Outra Unidade A')
            ->assertDontSee('Unidade B');

        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1)->assertOk();
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A2)->assertForbidden();
    }

    public function test_gestor_municipal_ve_suas_duas_escolas_mas_nao_de_outra_rede(): void
    {
        $user = $this->criarUsuario('direcao-rede@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');

        $this->actingAs($user)->get('/m/municipio-a/escolas')
            ->assertOk()
            ->assertSee('Unidade A')
            ->assertSee('Outra Unidade A')
            ->assertDontSee('Unidade B');

        $this->get('/m/municipio-b/escolas')->assertForbidden();
    }

    public function test_professor_sem_vinculo_de_escola_nao_lista_dados(): void
    {
        $user = $this->criarUsuario('professor@example.invalid');
        $this->vincular($user, $this->municipioA, 'teacher');

        $this->actingAs($user)->get('/m/municipio-a/escolas')->assertForbidden();
    }

    public function test_identificador_de_escola_de_outra_rede_nao_e_encontrado(): void
    {
        $user = $this->criarUsuario('gestor-estrangeiro@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_B1)->assertNotFound();
    }
}
