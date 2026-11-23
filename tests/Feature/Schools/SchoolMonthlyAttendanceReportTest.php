<?php

namespace Tests\Feature\Schools;

use App\Attendance\MonthlyAttendanceReport;
use App\Models\Tenant\AttendanceMark;
use App\Models\Tenant\AttendanceSession;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class SchoolMonthlyAttendanceReportTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private string $yearId;

    private string $classId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2027-04-15 09:00:00'));

        [$this->yearId, $this->classId] = $this->tenant(function (): array {
            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(),
                'year' => 2027,
                'start_date' => '2027-02-01',
                'end_date' => '2027-12-18',
                'status' => 'active',
            ]);
            $class = SchoolClass::query()->create([
                'id' => (string) Str::uuid(),
                'school_id' => self::ESCOLA_A1,
                'school_year_id' => $year->id,
                'name' => '2º Ano A',
                'stage' => 'Ensino Fundamental',
                'modality' => 'Regular',
                'shift' => 'Manhã',
                'status' => 'active',
            ]);

            return [$year->id, $class->id];
        });
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function tenant(callable $callback): mixed
    {
        return app(TenantContext::class)->runFor($this->municipioA, $callback);
    }

    private function login(string $role, array $schools = [self::ESCOLA_A1]): void
    {
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schools);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp()),
        ])->assertRedirect();
    }

    private function url(string $school = self::ESCOLA_A1, ?string $class = null): string
    {
        return '/m/municipio-a/escolas/'.$school.'/turmas/'.($class ?? $this->classId).'/frequencia-mensal';
    }

    private function addStudent(
        string $name,
        string $status = 'active',
        ?string $startedAt = null,
        ?string $endedAt = null,
    ): string {
        return $this->tenant(function () use ($name, $status, $startedAt, $endedAt): string {
            $student = Student::query()->create([
                'id' => (string) Str::uuid(), 'full_name' => $name,
            ]);
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $student->id,
                'school_id' => self::ESCOLA_A1,
                'school_year_id' => $this->yearId,
                'school_class_id' => $this->classId,
                'status' => $status,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
            ]);

            return $student->id;
        });
    }

    private function recordAttendance(string $day, array $statuses, ?string $observation = null): void
    {
        $this->tenant(function () use ($day, $statuses, $observation): void {
            $session = AttendanceSession::query()->create([
                'id' => (string) Str::uuid(),
                'school_class_id' => $this->classId,
                'attendance_date' => $day,
                'recorded_by' => $this->auditFixtureUser->id,
                'recorded_at' => now(),
                'correction_reason' => 'Ajuste interno fictício não exibível',
            ]);
            foreach ($statuses as $id => $status) {
                AttendanceMark::query()->create([
                    'id' => (string) Str::uuid(),
                    'attendance_session_id' => $session->id,
                    'student_id' => $id,
                    'status' => $status,
                    'observation' => $observation,
                ]);
            }
        });
    }

    public function test_grade_de_abril_ordenada_com_marcacoes_totais_e_atalhos(): void
    {
        $alvaro = $this->addStudent('Álvaro Fictício');
        $bruna = $this->addStudent('Bruna Fictícia');
        $this->recordAttendance('2027-04-01', [$alvaro => 'present', $bruna => 'present']);
        $this->recordAttendance('2027-04-02', [$alvaro => 'absent', $bruna => 'present']);
        $this->recordAttendance('2027-04-03', [$alvaro => 'justified']);
        $this->login('teacher');

        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/turmas/'.$this->classId)->assertOk()
            ->assertSee('Relatório do mês');
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/turmas/'.$this->classId.'/chamada')->assertOk()
            ->assertSee('Relatório do mês');

        $this->get($this->url().'?mes=2027-04')->assertOk()
            ->assertSee('Relatório mensal de frequência')
            ->assertSee('Municipio A')
            ->assertSee('2º Ano A')
            ->assertSeeInOrder(['Álvaro Fictício', 'Bruna Fictícia'])
            ->assertSee('•')
            ->assertSee('>F<', false)
            ->assertSee('>J<', false)
            ->assertSee('33%')
            ->assertSee('100%')
            ->assertSee('Total de alunos por dia')
            ->assertSee('Imprimir')
            ->assertSee('monthly-attendance-print.js')
            ->assertSee('Assinatura do professor')
            ->assertSee('Assinatura da direção');
    }

    public function test_mes_sem_chamada_mostra_mensagem_sem_criar_presencas(): void
    {
        $this->addStudent('Estudante Fictício');
        $this->login('school_secretary');
        $this->get($this->url().'?mes=2027-05')->assertOk()
            ->assertSee('Nenhuma chamada registrada neste mês')
            ->assertDontSee('relatorio-mensal-tabela');

        $count = $this->tenant(fn (): int => AttendanceSession::query()->count());
        $this->assertSame(0, $count);
    }

    public function test_transferencia_no_meio_do_mes_limita_os_dias_e_totais(): void
    {
        $student = $this->addStudent('Transferência Fictícia', 'transferred', '2027-04-05', '2027-04-10');
        $this->recordAttendance('2027-04-02', [$student => 'present']);
        $this->recordAttendance('2027-04-09', [$student => 'absent']);
        $this->recordAttendance('2027-04-12', [$student => 'present']);
        $this->login('school_director');

        $this->get($this->url().'?mes=2027-04')->assertOk()
            ->assertSee('Transferência Fictícia')
            ->assertSee('Transferido em 10/04/2027')
            ->assertSee('0%')
            ->assertSee('relatorio-fora-vinculo')
            ->assertSee('Total de alunos por dia');

        $report = app(MonthlyAttendanceReport::class);
        $result = $this->tenant(fn () => $report->forClass($this->reportClass(), Carbon::parse('2027-04-01')));
        $row = $result['students']->first();
        $this->assertSame(['', 'F', ''], array_values($row['marks']));
        $this->assertSame(['0', '1', '0'], array_map(
            fn ($session): string => (string) $result['dailyTotals'][$session->id],
            $result['sessions']->all(),
        ));
        $this->assertSame(['present' => 0, 'absent' => 1, 'justified' => 0], $row['totals']);
    }

    private function reportClass(): SchoolClass
    {
        return SchoolClass::query()->with('schoolYear')->findOrFail($this->classId);
    }

    public function test_mes_padrao_e_navegacao_dentro_do_ano_letivo(): void
    {
        $this->login('school_secretary');

        $this->get($this->url())->assertOk()->assertSee('Abril de 2027');
        $this->get($this->url().'?mes=2027-02')->assertOk()
            ->assertSee('Fevereiro de 2027')
            ->assertSee('mes=2027-03')
            ->assertDontSee('mes=2027-01');
        $this->get($this->url().'?mes=2027-12')->assertOk()
            ->assertSee('Dezembro de 2027')
            ->assertSee('mes=2027-11')
            ->assertDontSee('mes=2028-01');
        $this->get($this->url().'?mes=2027-01')->assertRedirect()->assertSessionHasErrors('mes');
        $this->get($this->url().'?mes=2027-13')->assertRedirect()->assertSessionHasErrors('mes');
    }

    public function test_professor_da_escola_pode_consultar_mas_outra_escola_recebe_403(): void
    {
        $this->login('teacher', [self::ESCOLA_A1]);
        $this->get($this->url())->assertOk();
        $other = $this->tenant(fn (): string => SchoolClass::query()->create([
            'id' => (string) Str::uuid(),
            'school_id' => self::ESCOLA_A2,
            'school_year_id' => $this->yearId,
            'name' => 'Turma fictícia B',
            'stage' => 'Ensino Fundamental',
            'modality' => 'Regular',
            'shift' => 'Tarde',
            'status' => 'active',
        ])->id);
        $this->get($this->url(self::ESCOLA_A2, $other))->assertForbidden();
        $this->get($this->url(self::ESCOLA_A2, $this->classId))->assertForbidden();
    }

    public function test_dados_sensiveis_observacoes_fotos_e_telefones_nao_entram_no_relatorio(): void
    {
        $student = $this->addStudent('Pessoa Fictícia');
        $this->recordAttendance('2027-04-10', [$student => 'justified'], 'Laudo médico fictício sigiloso');
        $this->tenant(function () use ($student): void {
            Student::query()->findOrFail($student)->forceFill(['photo_path' => 'fotos/privada-ficticia.jpg'])->save();
        });
        $this->login('school_secretary');

        $this->get($this->url())->assertOk()
            ->assertSee('Pessoa Fictícia')
            ->assertDontSee('Laudo médico')
            ->assertDontSee('Ajuste interno fictício')
            ->assertDontSee('privada-ficticia')
            ->assertDontSee('tel:')
            ->assertDontSee('foto-id-');
    }

    public function test_grade_usa_no_maximo_tres_consultas_sem_n_mais_um(): void
    {
        $enrollments = [];
        $students = [];
        $now = now()->toDateTimeString();
        for ($i = 0; $i < 120; $i++) {
            $id = (string) Str::uuid();
            $students[] = [
                'id' => $id,
                'full_name' => 'Aluno Fictício '.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $enrollments[] = [
                'id' => (string) Str::uuid(),
                'student_id' => $id,
                'school_id' => self::ESCOLA_A1,
                'school_year_id' => $this->yearId,
                'school_class_id' => $this->classId,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        $this->tenant(function () use ($enrollments, $students): void {
            DB::connection('tenant')->table('students')->insert($students);
            DB::connection('tenant')->table('student_enrollments')->insert($enrollments);
        });
        $this->recordAttendance('2027-04-14', [$students[0]['id'] => 'present']);
        $this->login('school_secretary');

        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            if ($query->connectionName === 'tenant' && preg_match('/\b(attendance_sessions|attendance_marks|student_enrollments)\b/i', $query->sql)) {
                $queries[] = $query->sql;
            }
        });

        $this->get($this->url())->assertOk()->assertSee('Aluno Fictício 119');
        $this->assertCount(3, $queries, 'Consultas da grade devem ser sessões, alunos e marcas, mesmo com 120 alunos.');
    }
}
