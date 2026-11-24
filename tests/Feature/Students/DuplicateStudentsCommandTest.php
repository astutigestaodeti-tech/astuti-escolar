<?php

namespace Tests\Feature\Students;

use App\Models\Tenant\AttendanceMark;
use App\Models\Tenant\AttendanceSession;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\Guardian;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentGuardian;
use App\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class DuplicateStudentsCommandTest extends TwoMunicipalityTestCase
{
    /** @var array<string, string> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = $this->tenant(function (): array {
            $year = SchoolYear::query()->create(['id' => (string) Str::uuid(), 'year' => 2027, 'start_date' => '2027-02-01', 'end_date' => '2027-12-18', 'status' => 'active']);
            $class = SchoolClass::query()->create(['id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id, 'name' => '1º Ano A', 'stage' => 'Ensino Fundamental', 'modality' => 'Regular', 'shift' => 'Manhã', 'status' => 'active']);
            $make = function (string $name, ?string $birth, string $school = self::ESCOLA_A1) use ($year, $class): string {
                $student = new Student;
                $student->forceFill(['id' => (string) Str::uuid(), 'full_name' => $name, 'birth_date' => $birth])->save();
                StudentEnrollment::query()->create(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'school_id' => $school, 'school_year_id' => $year->id, 'school_class_id' => $school === self::ESCOLA_A1 ? $class->id : null, 'status' => 'active']);

                return $student->id;
            };
            $ids = [
                'original' => $make('Laura Fictícia Pinto', '2019-04-02'),
                'copia' => $make('laura ficticia  pinto', null),
                'com_chamada' => $make('Laura Fictícia Pinto', '2019-04-02'),
                // Homônimos com nascimentos diferentes: pessoas diferentes, nunca agrupados.
                'outra_data' => $make('Davi Fictício Souza', '2018-01-01'),
                'outra_data_2' => $make('Davi Fictício Souza', '2019-01-01'),
                'outra_escola' => $make('Ryan Fictício Leme', null, self::ESCOLA_A2),
                'ryan' => $make('Ryan Fictício Leme', null),
            ];
            // O original tem responsável: é o cadastro que fica.
            $guardian = Guardian::query()->create(['id' => (string) Str::uuid(), 'full_name' => 'Mãe Fictícia', 'phone' => '11987654321']);
            StudentGuardian::query()->create(['id' => (string) Str::uuid(), 'student_id' => $ids['original'], 'guardian_id' => $guardian->id, 'relationship' => 'mother', 'is_primary_contact' => true]);
            // Uma cópia já tem chamada lançada: não sai automaticamente.
            $session = AttendanceSession::query()->create(['id' => (string) Str::uuid(), 'school_class_id' => $class->id, 'attendance_date' => '2027-03-01', 'recorded_by' => $this->auditFixtureUser->id, 'recorded_at' => now()]);
            AttendanceMark::query()->create(['id' => (string) Str::uuid(), 'attendance_session_id' => $session->id, 'student_id' => $ids['com_chamada'], 'status' => 'present']);

            return $ids;
        });
    }

    private function tenant(callable $callback): mixed
    {
        return app(TenantContext::class)->runFor($this->municipioA, $callback);
    }

    public function test_lista_sem_alterar_e_arquiva_so_as_copias_sem_registros_com_auditoria(): void
    {
        $this->artisan('astuti:alunos-repetidos')
            ->expectsOutputToContain('será arquivado (sem registros próprios)')
            ->expectsOutputToContain('tem registros próprios: attendance_marks')
            ->expectsOutputToContain('está matriculado em outra escola ou ano')
            ->expectsOutputToContain('Nada foi alterado.')
            ->assertSuccessful();
        $this->assertSame(7, $this->tenant(fn (): int => Student::query()->count()));

        $this->artisan('astuti:alunos-repetidos', ['--confirmar' => 'SIM', '--responsavel' => 'x'])->assertExitCode(2);
        $this->artisan('astuti:alunos-repetidos', ['--confirmar' => 'REMOVER-REPETIDOS', '--responsavel' => 'ninguem@example.invalid'])->assertExitCode(2);

        $this->artisan('astuti:alunos-repetidos', ['--confirmar' => 'REMOVER-REPETIDOS', '--responsavel' => $this->auditFixtureUser->email])
            ->expectsOutputToContain('arquivado.')
            ->assertSuccessful();

        $this->tenant(function (): void {
            $remaining = Student::query()->pluck('id')->all();
            $this->assertNotContains($this->ids['copia'], $remaining);
            foreach (['original', 'com_chamada', 'outra_data', 'outra_data_2', 'outra_escola', 'ryan'] as $key) {
                $this->assertContains($this->ids[$key], $remaining, $key);
            }
            // Ryan: um na escola A1 e outro na A2, sem data. Nenhum sai: pode ser transferência.
            $this->assertCount(6, $remaining);
            $this->assertTrue(Student::withTrashed()->whereKey($this->ids['copia'])->value('deleted_at') !== null);
            $this->assertTrue(AuditLog::query()->where('auditable_id', $this->ids['copia'])->where('action', 'deleted')->exists());
        });
    }
}
