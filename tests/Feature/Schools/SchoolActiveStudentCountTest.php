<?php

namespace Tests\Feature\Schools;

use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class SchoolActiveStudentCountTest extends TwoMunicipalityTestCase
{
    public function test_escola_autorizada_exibe_total_de_alunos_ativos_sem_contar_matriculas_duplicadas(): void
    {
        $user = $this->criarUsuario('contagem-secretaria@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);

        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $year2026 = $this->anoLetivo(2026);
            $year2027 = $this->anoLetivo(2027);
            $studentOne = $this->aluno('Alice Fictícia');
            $studentTwo = $this->aluno('Bruna Fictícia');
            $studentInactive = $this->aluno('Carlos sem vínculo ativo');
            $studentOther = $this->aluno('Dora de Outra Escola');

            $this->vinculo($studentOne, self::ESCOLA_A1, $year2026->id);
            $this->vinculo($studentOne, self::ESCOLA_A1, $year2027->id);
            $this->vinculo($studentTwo, self::ESCOLA_A1, $year2026->id);
            $this->vinculo($studentInactive, self::ESCOLA_A1, $year2026->id, 'completed');
            $this->vinculo($studentOther, self::ESCOLA_A2, $year2026->id);
        });

        $this->actingAs($user)->get('/m/municipio-a/escolas')
            ->assertOk()
            ->assertSee('Unidade A')
            ->assertSeeText('2 alunos ativos')
            ->assertDontSee('Outra Unidade A')
            ->assertDontSee('Dora de Outra Escola');

        $this->assertFalse(app(TenantContext::class)->hasActive());
    }

    public function test_escola_sem_alunos_ativos_exibe_zero(): void
    {
        $user = $this->criarUsuario('contagem-gestor@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');

        $this->actingAs($user)->get('/m/municipio-a/escolas')
            ->assertOk()
            ->assertSee('Unidade A')
            ->assertSee('Outra Unidade A')
            ->assertSeeText('0 alunos ativos');
    }

    private function aluno(string $nome): string
    {
        $id = (string) Str::uuid();
        Student::query()->create(['id' => $id, 'full_name' => $nome]);

        return $id;
    }

    private function vinculo(string $studentId, string $schoolId, string $schoolYearId, string $status = 'active'): void
    {
        StudentEnrollment::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $studentId,
            'school_id' => $schoolId,
            'school_year_id' => $schoolYearId,
            'status' => $status,
        ]);
    }

    private function anoLetivo(int $year): SchoolYear
    {
        return SchoolYear::query()->firstOrCreate(
            ['year' => $year],
            [
                'id' => (string) Str::uuid(),
                'start_date' => sprintf('%d-02-01', $year),
                'end_date' => sprintf('%d-12-18', $year),
                'status' => 'active',
            ],
        );
    }
}
