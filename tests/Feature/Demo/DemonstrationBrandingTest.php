<?php

namespace Tests\Feature\Demo;

use Tests\TestCase;

class DemonstrationBrandingTest extends TestCase
{
    public function test_monte_sereno_dashboard_displays_institutional_identity_and_original_crest(): void
    {
        $this->get('/demonstracao')
            ->assertOk()
            ->assertSee('Prefeitura Municipal de Monte Sereno')
            ->assertSee('Secretaria Municipal de Educação')
            ->assertSee('Brasão de Monte Sereno')
            ->assertSee('images/brasao-monte-sereno.webp');

        $image = public_path('images/brasao-monte-sereno.webp');
        $this->assertFileExists($image);
        $this->assertSame(IMAGETYPE_WEBP, getimagesize($image)[2]);
    }

    public function test_institutional_identity_remains_visible_across_demo_navigation(): void
    {
        $this->get('/demonstracao?tela=alunos&busca=Marina')
            ->assertOk()
            ->assertSee('Prefeitura Municipal de Monte Sereno')
            ->assertSee('Marina Alves')
            ->assertSee('Dados 100% fictícios');
    }
}
