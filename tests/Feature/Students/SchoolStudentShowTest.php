<?php

namespace Tests\Feature\Students;

use App\Models\Platform\Municipality;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class SchoolStudentShowTest extends TwoMunicipalityTestCase
{
    public function test_secretaria_abre_ficha_do_aluno_com_vinculo_ativo_na_escola(): void
    {
        $user = $this->criarUsuario('ficha-autorizada@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);
        $studentId = $this->matricularAluno($this->municipioA, self::ESCOLA_A1, 'Maria da Unidade A', 'active', '2017-04-02');

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$studentId)
            ->assertOk()
            ->assertSee('Maria da Unidade A')
            ->assertSee('2017')
            ->assertSee('2027')
            ->assertSee('Unidade A')
            ->assertSee('Somente consulta');

        $this->assertFalse(app(TenantContext::class)->hasActive());
    }

    public function test_ficha_nao_expoe_aluno_de_outra_escola_ou_sem_vinculo_ativo(): void
    {
        $user = $this->criarUsuario('ficha-escopo@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);

        $studentOther = $this->matricularAluno($this->municipioA, self::ESCOLA_A2, 'Aluno da Outra Escola');
        $studentInactive = $this->matricularAluno($this->municipioA, self::ESCOLA_A1, 'Aluno Desvinculado', 'completed');

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$studentOther)
            ->assertNotFound();
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$studentInactive)
            ->assertNotFound();
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A2.'/alunos/'.$studentOther)
            ->assertForbidden();
        $this->get('/m/municipio-b/escolas/'.self::ESCOLA_B1.'/alunos/'.$studentOther)
            ->assertForbidden();

        $this->assertFalse(app(TenantContext::class)->hasActive());
    }

    public function test_visitante_nao_pode_abrir_ficha_de_aluno(): void
    {
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.Str::uuid())
            ->assertRedirect('/login');
    }

    public function test_nome_malicioso_e_escapado_e_lista_aponta_para_ficha(): void
    {
        $user = $this->criarUsuario('ficha-html@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');
        $studentId = $this->matricularAluno($this->municipioA, self::ESCOLA_A1, '<script>alert(1)</script>', 'active', null);

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos')
            ->assertOk()
            ->assertSee('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$studentId, false);

        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$studentId)
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('Não informado');
    }

    private function matricularAluno(
        Municipality $municipio,
        string $escola,
        string $nome,
        string $status = 'active',
        ?string $nascimento = null,
    ): string {
        $id = (string) Str::uuid();

        app(TenantContext::class)->runFor($municipio, function () use ($id, $escola, $nome, $status, $nascimento): void {
            $year = SchoolYear::query()->firstOrCreate(
                ['year' => 2027],
                [
                    'id' => (string) Str::uuid(),
                    'start_date' => '2027-02-01',
                    'end_date' => '2027-12-18',
                    'status' => 'active',
                ],
            );

            Student::query()->create([
                'id' => $id,
                'full_name' => $nome,
                'birth_date' => $nascimento,
            ]);

            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $id,
                'school_id' => $escola,
                'school_year_id' => $year->id,
                'status' => $status,
            ]);
        });

        return $id;
    }
}
