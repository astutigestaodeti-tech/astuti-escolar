<?php

namespace Tests\Feature\Demo;

use Tests\TestCase;

class DemonstrationScreenTest extends TestCase
{
    public function test_fictitious_monte_sereno_dashboard_has_clear_warning_and_main_actions(): void
    {
        $this->get('/demonstracao')
            ->assertOk()
            ->assertSee('Monte Sereno')
            ->assertSee('Ambiente de demonstração')
            ->assertSee('Dados 100% fictícios')
            ->assertSee('1.284')
            ->assertSee('Alunos atuais')
            ->assertSee('Importar documentos');
    }

    public function test_schools_and_students_have_example_records_only(): void
    {
        $this->get('/demonstracao?tela=escolas')
            ->assertOk()
            ->assertSee('EMEF Caminhos do Saber')
            ->assertSee('EMEI Jardim das Flores');

        $this->get('/demonstracao?tela=alunos')
            ->assertOk()
            ->assertSee('Marina Alves')
            ->assertSee('Cadastro fictício');
    }

    public function test_import_screen_is_marked_as_visual_simulation(): void
    {
        $this->get('/demonstracao?tela=importacoes')
            ->assertOk()
            ->assertSee('Etapas da importação')
            ->assertSee('Simulação visual')
            ->assertSee('Conferência humana')
            ->assertSee('Nenhum arquivo será enviado');
    }

    public function test_invalid_demo_screen_is_not_found(): void
    {
        $this->get('/demonstracao?tela=administrador')
            ->assertNotFound();
    }

    public function test_demonstracao_antiga_fica_fora_da_vps_sem_a_opcao_do_notebook(): void
    {
        app()->detectEnvironment(fn () => 'local');
        config()->set('app.demonstracao', false);
        $this->get('/demonstracao')->assertNotFound();
        $this->get('/login')->assertDontSee('Ver a demonstração de Monte Sereno');

        config()->set('app.demonstracao', true);
        $this->get('/demonstracao')->assertOk();
        $this->get('/login')->assertSee('Ver a demonstração de Monte Sereno');
    }

    public function test_demo_is_unavailable_when_running_in_production(): void
    {
        $originalEnvironment = app()->environment();
        app()->instance('env', 'production');

        try {
            $this->get('/demonstracao')->assertNotFound();
        } finally {
            app()->instance('env', $originalEnvironment);
        }
    }
}
