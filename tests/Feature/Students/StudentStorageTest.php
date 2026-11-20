<?php

namespace Tests\Feature\Students;

use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Tests\Support\TwoMunicipalityTestCase;

class StudentStorageTest extends TwoMunicipalityTestCase
{
    public function test_schema_de_alunos_e_vinculos_existe_apenas_no_banco_municipal(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $this->assertTrue(Schema::connection('tenant')->hasTable('students'));
            $this->assertTrue(Schema::connection('tenant')->hasTable('student_enrollments'));
            $this->assertFalse(Schema::connection('platform')->hasTable('students'));
        });
    }

    public function test_identidade_do_aluno_permanece_isolada_entre_municipios(): void
    {
        $context = app(TenantContext::class);

        $context->runFor($this->municipioA, function (): void {
            Student::query()->create([
                'id' => (string) Str::uuid(),
                'full_name' => 'Alice do Município A',
                'birth_date' => '2015-04-20',
            ]);
        });

        $context->runFor($this->municipioB, function (): void {
            $this->assertSame(0, Student::query()->count());
            Student::query()->create([
                'id' => (string) Str::uuid(),
                'full_name' => 'Bento do Município B',
                'birth_date' => null,
            ]);
            $this->assertSame(['Bento do Município B'], Student::query()->pluck('full_name')->all());
        });

        $context->runFor($this->municipioA, function (): void {
            $this->assertSame(['Alice do Município A'], Student::query()->pluck('full_name')->all());
        });

        $this->assertFalse($context->hasActive());
    }

    public function test_vinculo_academico_referencia_aluno_escola_e_ano_no_mesmo_banco(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $student = Student::query()->create([
                'id' => (string) Str::uuid(),
                'full_name' => 'Clara da Escola A',
            ]);

            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(),
                'year' => 2026,
                'start_date' => '2026-02-02',
                'end_date' => '2026-12-18',
                'status' => 'active',
            ]);

            $enrollment = StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $student->id,
                'school_id' => self::ESCOLA_A1,
                'school_year_id' => $year->id,
                'status' => 'active',
            ]);

            $this->assertSame('Clara da Escola A', $enrollment->student->full_name);
            $this->assertSame('Unidade A', $enrollment->school->name);
            $this->assertSame(2026, $enrollment->schoolYear->year);
            $this->assertSame(1, $student->enrollments()->count());
        });
    }

    public function test_vinculo_rejeita_escola_inexistente_antes_da_chave_estrangeira(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $student = Student::query()->create([
                'id' => (string) Str::uuid(),
                'full_name' => 'Denise da Escola A',
            ]);

            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(),
                'year' => 2027,
                'start_date' => '2027-02-01',
                'end_date' => '2027-12-17',
                'status' => 'active',
            ]);

            $this->expectException(LogicException::class);

            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $student->id,
                'school_id' => '99999999-9999-4999-8999-999999999999',
                'school_year_id' => $year->id,
                'status' => 'active',
            ]);
        });
    }

    public function test_consulta_a_identidades_sem_contexto_municipal_e_bloqueada(): void
    {
        $this->expectException(LogicException::class);
        Student::query()->count();
    }
}
