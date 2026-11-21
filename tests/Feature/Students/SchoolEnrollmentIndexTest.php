<?php

namespace Tests\Feature\Students;

use App\Models\Platform\Municipality;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Tenancy\TenantContext;
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

        $pageOne = $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/matriculas');
        $pageOne->assertOk()->assertSee('Aluno 01')->assertDontSee('Aluno 26');
        $this->assertSame(26, $pageOne->viewData('enrollments')->total());

        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/matriculas?page=2')
            ->assertOk()
            ->assertSee('Aluno 26')
            ->assertDontSee('Aluno 01');
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
