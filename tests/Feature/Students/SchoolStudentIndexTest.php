<?php

namespace Tests\Feature\Students;

use App\Models\Platform\Municipality;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class SchoolStudentIndexTest extends TwoMunicipalityTestCase
{
    public function test_secretaria_consulta_somente_alunos_ativos_da_escola_autorizada(): void
    {
        $user = $this->criarUsuario('alunos-secretaria@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);

        $this->matricularAluno($this->municipioA, self::ESCOLA_A1, 'Alice da Unidade A');
        $this->matricularAluno($this->municipioA, self::ESCOLA_A2, 'Bruno da Outra Unidade A');
        $this->matricularAluno($this->municipioB, self::ESCOLA_B1, 'Carlos da Unidade B');
        $this->matricularAluno($this->municipioA, self::ESCOLA_A1, 'Dora com vínculo encerrado', 'completed');

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos')
            ->assertOk()
            ->assertSee('Alice da Unidade A')
            ->assertDontSee('Bruno da Outra Unidade A')
            ->assertDontSee('Carlos da Unidade B')
            ->assertDontSee('Dora com vínculo encerrado')
            ->assertSee('Somente consulta');

        $this->assertFalse(app(TenantContext::class)->hasActive());
    }

    public function test_escola_sem_permissao_nao_expoe_alunos_por_url_direta(): void
    {
        $user = $this->criarUsuario('alunos-escopo@example.invalid');
        $this->vincular($user, $this->municipioA, 'teacher', [self::ESCOLA_A1]);
        $this->matricularAluno($this->municipioA, self::ESCOLA_A2, 'Estudante restrito');

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A2.'/alunos')
            ->assertForbidden();
        $this->get('/m/municipio-b/escolas/'.self::ESCOLA_B1.'/alunos')
            ->assertForbidden();
        $this->get('/m/municipio-a/escolas/99999999-9999-4999-8999-999999999999/alunos')
            ->assertNotFound();
    }

    public function test_visitante_nao_pode_consultar_alunos(): void
    {
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos')
            ->assertRedirect('/login');
    }

    public function test_tela_exibe_estado_vazio_quando_escola_nao_tem_vinculos_ativos(): void
    {
        $user = $this->criarUsuario('alunos-vazio@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos')
            ->assertOk()
            ->assertSee('Nenhum aluno ativo')
            ->assertSee('Unidade A');
    }

    public function test_busca_por_nome_nao_interpreta_porcentagem_ou_sublinhado_como_curingas(): void
    {
        $user = $this->criarUsuario('alunos-busca@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');
        $this->matricularAluno($this->municipioA, self::ESCOLA_A1, 'Aline 100% fictícia');
        $this->matricularAluno($this->municipioA, self::ESCOLA_A1, 'Paulo Fictício');
        $this->matricularAluno($this->municipioA, self::ESCOLA_A1, 'Beatriz_A');
        $this->matricularAluno($this->municipioA, self::ESCOLA_A1, 'BeatrizXA');

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos?busca=%25')
            ->assertOk()
            ->assertSee('Aline 100% fictícia')
            ->assertDontSee('Paulo Fictício')
            ->assertDontSee('Beatriz_A');

        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos?busca=_')
            ->assertOk()
            ->assertSee('Beatriz_A')
            ->assertDontSee('BeatrizXA');
    }

    public function test_busca_por_nome_parcial_nao_diferencia_maiusculas_de_minusculas_no_postgresql(): void
    {
        $user = $this->criarUsuario('alunos-busca-caixa@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);

        $this->matricularAluno($this->municipioA, self::ESCOLA_A1, 'Maria do Campo');
        $this->matricularAluno($this->municipioA, self::ESCOLA_A1, 'MARINA dos Lagos');
        $this->matricularAluno($this->municipioA, self::ESCOLA_A1, 'Bruno da Unidade');
        $this->matricularAluno($this->municipioA, self::ESCOLA_A2, 'Marcos de Outra Escola');

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos?busca=mari')
            ->assertOk()
            ->assertSee('Maria do Campo')
            ->assertSee('MARINA dos Lagos')
            ->assertDontSee('Bruno da Unidade')
            ->assertDontSee('Marcos de Outra Escola');
    }

    public function test_nome_exibido_e_escapado_no_html(): void
    {
        $user = $this->criarUsuario('alunos-escape@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');
        $this->matricularAluno($this->municipioA, self::ESCOLA_A1, '<script>alert(1)</script>');

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_multiplos_vinculos_do_mesmo_aluno_na_escola_nao_duplicam_linha(): void
    {
        $user = $this->criarUsuario('alunos-vinculos@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');

        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $student = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => 'Helena Fictícia']);
            foreach ([2026, 2027] as $yearNumber) {
                $year = $this->anoLetivo($yearNumber);
                StudentEnrollment::query()->create([
                    'id' => (string) Str::uuid(),
                    'student_id' => $student->id,
                    'school_id' => self::ESCOLA_A1,
                    'school_year_id' => $year->id,
                    'status' => 'active',
                ]);
            }
        });

        $response = $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos');
        $response->assertOk()->assertSee('Helena Fictícia');
        $this->assertSame(1, $response->viewData('students')->total());
    }

    public function test_paginacao_mostra_vinte_e_cinco_registros_por_pagina(): void
    {
        $user = $this->criarUsuario('alunos-paginacao@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');

        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $year = $this->anoLetivo(2026);
            foreach (range(1, 26) as $index) {
                $student = Student::query()->create([
                    'id' => (string) Str::uuid(),
                    'full_name' => sprintf('Aluno %02d de Teste', $index),
                ]);
                StudentEnrollment::query()->create([
                    'id' => (string) Str::uuid(),
                    'student_id' => $student->id,
                    'school_id' => self::ESCOLA_A1,
                    'school_year_id' => $year->id,
                    'status' => 'active',
                ]);
            }
        });

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos')
            ->assertOk()
            ->assertSee('Aluno 01 de Teste')
            ->assertDontSee('Aluno 26 de Teste');

        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos?page=2')
            ->assertOk()
            ->assertSee('Aluno 26 de Teste')
            ->assertDontSee('Aluno 01 de Teste');
    }

    public function test_nao_existe_rota_publica_para_gravar_aluno(): void
    {
        $user = $this->criarUsuario('alunos-gravacao@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');

        $this->actingAs($user)->post('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos', [
            'full_name' => 'Não pode ser gravado',
        ])->assertStatus(405);
    }

    public function test_ficha_da_escola_oferece_acesso_a_consulta_de_alunos(): void
    {
        $user = $this->criarUsuario('alunos-ficha@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1)
            ->assertOk()
            ->assertSee('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos');
    }

    private function matricularAluno(Municipality $municipio, string $escola, string $nome, string $status = 'active'): void
    {
        app(TenantContext::class)->runFor($municipio, function () use ($escola, $nome, $status): void {
            $year = $this->anoLetivo(2026);
            $student = Student::query()->create([
                'id' => (string) Str::uuid(),
                'full_name' => $nome,
            ]);
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $student->id,
                'school_id' => $escola,
                'school_year_id' => $year->id,
                'status' => $status,
            ]);
        });
    }

    private function anoLetivo(int $yearNumber): SchoolYear
    {
        return SchoolYear::query()->firstOrCreate(
            ['year' => $yearNumber],
            [
                'id' => (string) Str::uuid(),
                'start_date' => sprintf('%d-02-01', $yearNumber),
                'end_date' => sprintf('%d-12-18', $yearNumber),
                'status' => 'active',
            ],
        );
    }
}
