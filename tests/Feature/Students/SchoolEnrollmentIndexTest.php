<?php

namespace Tests\Feature\Students;

use App\Models\Platform\Municipality;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class SchoolEnrollmentIndexTest extends TwoMunicipalityTestCase
{
    public function test_secretaria_consulta_apenas_matriculas_ativas_da_escola_autorizada(): void
    {
        $user = $this->criarUsuario('matriculas-autorizadas@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);

        $alunoId = $this->matricular($this->municipioA, self::ESCOLA_A1, 'Ana da Unidade A', 'active', 2027, '2027-02-10');
        $this->matricular($this->municipioA, self::ESCOLA_A1, 'Bruno concluído', 'completed', 2026);
        $this->matricular($this->municipioA, self::ESCOLA_A2, 'Carlos de outra escola');
        $this->matricular($this->municipioB, self::ESCOLA_B1, 'Dora de outro município');

        $response = $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/matriculas');
        $response->assertOk()
            ->assertSee('Matrículas ativas')
            ->assertSee('Ana da Unidade A')
            ->assertSee('Ano letivo 2027')
            ->assertSee('10/02/2027')
            ->assertSee('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$alunoId, false)
            ->assertDontSee('Bruno concluído')
            ->assertDontSee('Carlos de outra escola')
            ->assertDontSee('Dora de outro município');
        $this->assertSame(1, $response->viewData('enrollments')->total());
        $this->assertFalse(app(TenantContext::class)->hasActive());
    }

    public function test_matriculas_respeitam_permissao_de_escola_municipio_e_somente_leitura(): void
    {
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/matriculas')
            ->assertRedirect('/login');

        $user = $this->criarUsuario('matriculas-escopo@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A2.'/matriculas')
            ->assertForbidden();
        $this->get('/m/municipio-b/escolas/'.self::ESCOLA_B1.'/matriculas')
            ->assertForbidden();
        $this->post('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/matriculas', [
            'student_id' => (string) Str::uuid(),
        ])->assertStatus(405);
    }

    public function test_busca_de_matriculas_ignora_caixa_e_trata_curingas_como_literais(): void
    {
        $user = $this->criarUsuario('matriculas-busca@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');
        $this->matricular($this->municipioA, self::ESCOLA_A1, 'Maria do Campo');
        $this->matricular($this->municipioA, self::ESCOLA_A1, 'MARINA das Flores');
        $this->matricular($this->municipioA, self::ESCOLA_A1, 'Mauro Fictício');
        $this->matricular($this->municipioA, self::ESCOLA_A1, 'Aluna 100% fictícia');
        $this->matricular($this->municipioA, self::ESCOLA_A2, 'Mariana da Escola B');

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/matriculas?busca=mari')
            ->assertOk()
            ->assertSee('Maria do Campo')
            ->assertSee('MARINA das Flores')
            ->assertDontSee('Mauro Fictício')
            ->assertDontSee('Mariana da Escola B');

        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/matriculas?busca=%25')
            ->assertOk()
            ->assertSee('Aluna 100% fictícia')
            ->assertDontSee('Maria do Campo')
            ->assertDontSee('Mauro Fictício');
    }

    public function test_paginacao_de_matriculas_mostra_vinte_e_cinco_por_pagina(): void
    {
        $user = $this->criarUsuario('matriculas-paginacao@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');

        foreach (range(1, 26) as $index) {
            $this->matricular($this->municipioA, self::ESCOLA_A1, sprintf('Aluno %02d', $index));
        }

        $pageOne = $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/matriculas?busca=Aluno');
        $pageOne->assertOk()->assertSee('Aluno 01')->assertDontSee('Aluno 26');
        $this->assertSame(26, $pageOne->viewData('enrollments')->total());

        parse_str((string) parse_url($pageOne->viewData('enrollments')->nextPageUrl(), PHP_URL_QUERY), $nextQuery);
        $this->assertSame('Aluno', $nextQuery['busca'] ?? null);
        $this->assertSame('2', (string) ($nextQuery['page'] ?? ''));

        $this->get($pageOne->viewData('enrollments')->nextPageUrl())
            ->assertOk()
            ->assertSee('Aluno 26')
            ->assertDontSee('Aluno 01');
    }

    public function test_matricula_arquivada_nao_aparece_na_consulta(): void
    {
        $user = $this->criarUsuario('matriculas-arquivadas@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);
        $arquivadoId = $this->matricular($this->municipioA, self::ESCOLA_A1, 'Aluno com Matrícula Arquivada');
        $this->matricular($this->municipioA, self::ESCOLA_A1, 'Aluno Preservado');

        app(TenantContext::class)->runFor($this->municipioA, function () use ($arquivadoId): void {
            StudentEnrollment::query()->where('student_id', $arquivadoId)->firstOrFail()
                ->deleteWithReason('Teste de arquivamento de matrícula fictícia', $this->auditFixtureUser->id);
        });

        $response = $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/matriculas');
        $response->assertOk()->assertSee('Aluno Preservado')->assertDontSee('Aluno com Matrícula Arquivada');
        $this->assertSame(1, $response->viewData('enrollments')->total());
        $this->assertFalse(app(TenantContext::class)->hasActive());
    }

    public function test_aluno_arquivado_com_matricula_ativa_legada_nao_aparece_na_consulta(): void
    {
        $user = $this->criarUsuario('matriculas-aluno-arquivado@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);
        $arquivadoId = $this->matricular($this->municipioA, self::ESCOLA_A1, 'Aluno Arquivado com Vínculo Legado');
        $this->matricular($this->municipioA, self::ESCOLA_A1, 'Aluno Preservado');

        app(TenantContext::class)->runFor($this->municipioA, function () use ($arquivadoId): void {
            // Simula dado legado inconsistente SOMENTE na base PostgreSQL descartável *_test.
            // O fluxo normal proíbe arquivar um aluno enquanto houver matrícula ativa.
            DB::connection('tenant')->table('students')->where('id', $arquivadoId)->update([
                'deleted_at' => now(),
                'deleted_by' => $this->auditFixtureUser->id,
                'deletion_reason' => 'Simulação de dado legado inconsistente',
            ]);
            $this->assertTrue(Student::query()->withTrashed()->findOrFail($arquivadoId)->trashed());
            $this->assertTrue(StudentEnrollment::query()
                ->where('student_id', $arquivadoId)
                ->where('status', 'active')->exists());
        });

        $response = $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/matriculas');
        $response->assertOk()->assertSee('Aluno Preservado')->assertDontSee('Aluno Arquivado com Vínculo Legado');
        $this->assertSame(1, $response->viewData('enrollments')->total());
        $this->assertFalse(app(TenantContext::class)->hasActive());
    }

    public function test_matriculas_exigem_confirmacao_do_segundo_fator(): void
    {
        $user = $this->criarUsuario('matriculas-totp@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);
        $user->forceFill([
            'two_factor_secret' => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/matriculas')
            ->assertRedirect(route('two-factor.challenge'));
        $this->assertFalse(app(TenantContext::class)->hasActive());
    }

    public function test_busca_de_matriculas_trata_sublinhado_e_barra_invertida_como_literais(): void
    {
        $user = $this->criarUsuario('matriculas-busca-literal@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');
        $this->matricular($this->municipioA, self::ESCOLA_A1, 'Aluno_Com_Sublinhado');
        $this->matricular($this->municipioA, self::ESCOLA_A1, 'Aluno Com Espaço');
        $this->matricular($this->municipioA, self::ESCOLA_A1, 'Aluno\\Com Barra');

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/matriculas?busca=%5F')
            ->assertOk()
            ->assertSee('Aluno_Com_Sublinhado')
            ->assertDontSee('Aluno Com Espaço')
            ->assertDontSee('Aluno\\Com Barra');

        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/matriculas?busca=%5C')
            ->assertOk()
            ->assertSee('Aluno\\Com Barra')
            ->assertDontSee('Aluno_Com_Sublinhado')
            ->assertDontSee('Aluno Com Espaço');
    }

    public function test_matriculas_ordenam_ano_decrescente_e_depois_nome(): void
    {
        $user = $this->criarUsuario('matriculas-ordenacao@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');
        $this->matricular($this->municipioA, self::ESCOLA_A1, 'Zeca do Ano Anterior', 'active', 2026);
        $this->matricular($this->municipioA, self::ESCOLA_A1, 'Beto do Ano Atual', 'active', 2027);
        $this->matricular($this->municipioA, self::ESCOLA_A1, 'Ana do Ano Atual', 'active', 2027);

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/matriculas')
            ->assertOk()
            ->assertSeeInOrder(['Ana do Ano Atual', 'Beto do Ano Atual', 'Zeca do Ano Anterior']);
    }

    public function test_matriculas_rejeitam_termo_de_busca_maior_que_cento_e_vinte_caracteres(): void
    {
        $user = $this->criarUsuario('matriculas-busca-longa@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/matriculas?busca='.str_repeat('x', 121))
            ->assertRedirect()
            ->assertSessionHasErrors('busca');
    }

    public function test_filtro_de_ano_lista_somente_anos_da_escola_autorizada(): void
    {
        $user = $this->criarUsuario('matriculas-filtro-anos@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);
        $this->matricular($this->municipioA, self::ESCOLA_A1, 'Ana Ano 2027', 'active', 2027);
        $this->matricular($this->municipioA, self::ESCOLA_A1, 'Bruna Ano 2026', 'active', 2026);
        $this->matricular($this->municipioA, self::ESCOLA_A1, 'Carlos Ano 2025 Concluído', 'completed', 2025);
        $this->matricular($this->municipioA, self::ESCOLA_A2, 'Outro Aluno Ano 2024', 'active', 2024);
        $this->matricular($this->municipioB, self::ESCOLA_B1, 'Outro Município Ano 2023', 'active', 2023);

        $response = $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/matriculas');
        $response->assertOk()
            ->assertSee('Ana Ano 2027')
            ->assertSee('Bruna Ano 2026')
            ->assertSee('name="ano_letivo"', false)
            ->assertSee('Todos os anos');
        $this->assertSame([2027, 2026], $response->viewData('availableYears'));
        $this->assertSame(2, $response->viewData('enrollments')->total());

        $filtered = $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/matriculas?ano_letivo=2026');
        $filtered->assertOk()
            ->assertSee('Bruna Ano 2026')
            ->assertDontSee('Ana Ano 2027')
            ->assertDontSee('Carlos Ano 2025 Concluído')
            ->assertDontSee('Outro Aluno Ano 2024')
            ->assertDontSee('Outro Município Ano 2023');
        $this->assertSame(2026, $filtered->viewData('selectedYear'));
        $this->assertSame(1, $filtered->viewData('enrollments')->total());
        $this->assertFalse(app(TenantContext::class)->hasActive());
    }

    public function test_filtro_de_ano_combina_busca_e_preserva_parametros_na_paginacao(): void
    {
        $user = $this->criarUsuario('matriculas-filtro-paginacao@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);

        foreach (range(1, 26) as $index) {
            $this->matricular($this->municipioA, self::ESCOLA_A1, sprintf('Aluno Filtrado %02d', $index), 'active', 2027);
        }
        $this->matricular($this->municipioA, self::ESCOLA_A1, 'Aluno de 2026', 'active', 2026);
        $this->matricular($this->municipioA, self::ESCOLA_A1, 'Outro de 2027', 'active', 2027);

        $first = $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/matriculas?busca=Aluno&ano_letivo=2027');
        $first->assertOk()->assertSee('Aluno Filtrado 01')->assertDontSee('Aluno Filtrado 26')->assertDontSee('Aluno de 2026');
        $this->assertSame(26, $first->viewData('enrollments')->total());
        $this->assertSame(2027, $first->viewData('selectedYear'));

        $nextUrl = $first->viewData('enrollments')->nextPageUrl();
        parse_str((string) parse_url($nextUrl, PHP_URL_QUERY), $params);
        $this->assertSame('Aluno', $params['busca'] ?? null);
        $this->assertSame('2027', (string) ($params['ano_letivo'] ?? ''));
        $this->assertSame('2', (string) ($params['page'] ?? ''));

        $this->get($nextUrl)
            ->assertOk()
            ->assertSee('Aluno Filtrado 26')
            ->assertDontSee('Aluno Filtrado 01')
            ->assertDontSee('Aluno de 2026');
    }

    public function test_filtro_de_ano_rejeita_ano_inexistente_na_escola_e_valor_invalido(): void
    {
        $user = $this->criarUsuario('matriculas-filtro-invalido@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);
        $this->matricular($this->municipioA, self::ESCOLA_A1, 'Aluno Atual', 'active', 2027);
        $this->matricular($this->municipioA, self::ESCOLA_A1, 'Aluno Concluído', 'completed', 2025);
        $this->matricular($this->municipioA, self::ESCOLA_A2, 'Aluno Outra Escola', 'active', 2026);

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/matriculas?ano_letivo=2026')
            ->assertRedirect()
            ->assertSessionHasErrors('ano_letivo');

        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/matriculas?ano_letivo=invalido')
            ->assertRedirect()
            ->assertSessionHasErrors('ano_letivo');
    }

    public function test_filtro_invalido_volta_para_busca_valida_e_exibe_erro_acessivel(): void
    {
        $user = $this->criarUsuario('matriculas-filtro-retorno@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);
        $this->matricular($this->municipioA, self::ESCOLA_A1, 'Aluno do Ano Atual', 'active', 2027);

        $url = '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/matriculas';
        $invalid = $this->actingAs($user)->get($url.'?busca=Aluno&ano_letivo=2025');
        $invalid->assertRedirect($url.'?busca=Aluno')
            ->assertSessionHasErrors('ano_letivo');

        // Segue o 302 como um navegador, preservando o cookie da sessão de teste.
        $this->followingRedirects()->get($url.'?busca=Aluno&ano_letivo=2025')
            ->assertOk()
            ->assertSee('Escolha um ano letivo disponível para esta escola.')
            ->assertSee('role="alert"', false)
            ->assertSee('Aluno do Ano Atual');

        $this->get($url.'?busca=Aluno&ano_letivo=2027')
            ->assertOk()
            ->assertDontSee('Escolha um ano letivo disponível para esta escola.');
        $this->assertFalse(app(TenantContext::class)->hasActive());
    }

    public function test_voltar_da_paginacao_retorna_a_pagina_anterior_da_mesma_lista(): void
    {
        $user = $this->criarUsuario('voltar-matriculas-pagina@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);

        $base = '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/matriculas';
        $origem = 'http://localhost:8000'.$base.'?busca=Ana&page=1';
        $this->actingAs($user)
            ->withHeader('referer', $origem)
            ->get($base.'?busca=Ana&page=2')
            ->assertOk()
            ->assertSee('Voltar à tela anterior')
            ->assertSee('class="voltar" href="'.str_replace('&', '&amp;', $origem).'"', false);

        $this->assertFalse(app(TenantContext::class)->hasActive());
    }

    private function matricular(
        Municipality $municipio,
        string $escola,
        string $nome,
        string $status = 'active',
        int $ano = 2027,
        ?string $inicio = null,
    ): string {
        $id = (string) Str::uuid();

        app(TenantContext::class)->runFor($municipio, function () use ($id, $escola, $nome, $status, $ano, $inicio): void {
            $year = SchoolYear::query()->firstOrCreate(
                ['year' => $ano],
                [
                    'id' => (string) Str::uuid(),
                    'start_date' => sprintf('%d-02-01', $ano),
                    'end_date' => sprintf('%d-12-18', $ano),
                    'status' => 'active',
                ],
            );

            Student::query()->create(['id' => $id, 'full_name' => $nome]);
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $id,
                'school_id' => $escola,
                'school_year_id' => $year->id,
                'status' => $status,
                'started_at' => $inicio,
            ]);
        });

        return $id;
    }
}
