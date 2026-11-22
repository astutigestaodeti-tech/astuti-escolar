<?php

namespace Tests\Feature\Schools;

use App\Models\Tenant\AttendanceMark;
use App\Models\Tenant\AttendanceSession;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class SchoolAttendanceTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private string $classId;

    private string $yearId;

    /** @var list<string> */
    private array $students = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2027-04-15 08:30:00'));

        [$this->yearId, $this->classId] = $this->tenant(function (): array {
            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(), 'year' => 2027,
                'start_date' => '2027-02-01', 'end_date' => '2027-12-18', 'status' => 'active',
            ]);
            $class = SchoolClass::query()->create([
                'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1,
                'school_year_id' => $year->id, 'name' => '2º A',
                'stage' => 'Ensino Fundamental', 'modality' => 'Regular',
                'shift' => 'Manhã', 'status' => 'active',
            ]);

            return [$year->id, $class->id];
        });
        $this->students[] = $this->student('Álvaro Fictício');
        $this->students[] = $this->student('Bruna Fictícia');
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

    private function url(string $escola = self::ESCOLA_A1, ?string $classId = null): string
    {
        return '/m/municipio-a/escolas/'.$escola.'/turmas/'.($classId ?? $this->classId).'/chamada';
    }

    private function student(string $name, string $status = 'active', ?string $classId = null, string $schoolId = self::ESCOLA_A1): string
    {
        return $this->tenant(function () use ($name, $status, $classId, $schoolId): string {
            $student = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => $name]);
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $student->id,
                'school_id' => $schoolId, 'school_year_id' => $this->yearId,
                'school_class_id' => $classId ?? $this->classId, 'status' => $status,
            ]);

            return $student->id;
        });
    }

    private function payload(string $date = '2027-04-15', array $statuses = [], array $notes = []): array
    {
        $marks = [];
        foreach ($this->students as $id) {
            $marks[$id] = [
                'situacao' => $statuses[$id] ?? 'present',
                'observacao' => $notes[$id] ?? '',
            ];
        }

        return ['data' => $date, 'presencas' => $marks];
    }

    private function seedAttendance(string $date, array $statuses): void
    {
        $this->tenant(function () use ($date, $statuses): void {
            $session = AttendanceSession::query()->create([
                'id' => (string) Str::uuid(), 'school_class_id' => $this->classId,
                'attendance_date' => $date, 'recorded_by' => $this->auditFixtureUser->id,
                'recorded_at' => now(),
            ]);
            foreach ($statuses as $studentId => $status) {
                AttendanceMark::query()->create([
                    'id' => (string) Str::uuid(), 'attendance_session_id' => $session->id,
                    'student_id' => $studentId, 'status' => $status,
                ]);
            }
        });
    }

    public function test_professor_registra_hoje_em_poucos_toques_com_foto_e_resumo(): void
    {
        $this->login('teacher');
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/turmas/'.$this->classId)
            ->assertOk()->assertSee('Fazer chamada de hoje');
        $this->get($this->url())->assertOk()
            ->assertSeeInOrder(['Álvaro Fictício', 'Bruna Fictícia'])
            ->assertSee('foto-id-pequena')
            ->assertSee('Salvar chamada')
            ->assertSee('value="present" checked', false)
            ->assertDontSee('Trocar foto');

        $this->post($this->url(), $this->payload(statuses: [$this->students[1] => 'absent']))
            ->assertRedirect();
        $this->get($this->url())->assertOk()->assertSee('1 presente, 1 falta');
        $saved = $this->tenant(fn () => [
            AttendanceSession::query()->count(),
            AttendanceMark::query()->orderBy('student_id')->pluck('status')->sort()->values()->all(),
        ]);
        $this->assertSame(1, $saved[0]);
        $this->assertSame(['absent', 'present'], $saved[1]);
    }

    public function test_mesma_data_corrige_sem_duplicar_e_registra_auditoria_sem_observacao(): void
    {
        $this->login('teacher');
        $this->post($this->url(), $this->payload())->assertRedirect();
        $note = 'Dado médico fictício sigiloso que não deve entrar na auditoria';
        $this->post($this->url(), $this->payload(
            statuses: [$this->students[0] => 'justified'],
            notes: [$this->students[0] => $note],
        ))->assertRedirect();

        $this->assertSame(1, $this->tenant(fn (): int => AttendanceSession::query()->count()));
        $this->assertSame(2, $this->tenant(fn (): int => AttendanceMark::query()->count()));
        $this->assertSame($note, $this->tenant(fn (): ?string => AttendanceMark::query()
            ->where('student_id', $this->students[0])->first()?->observation));
        $audits = $this->tenant(fn () => AuditLog::query()->whereIn('auditable_type', [AttendanceMark::class, AttendanceSession::class])->get());
        $this->assertTrue($audits->contains(fn ($log): bool => $log->action === 'attendance_session.created'));
        $this->assertTrue($audits->contains(fn ($log): bool => $log->action === 'attendance_session.corrected'));
        $this->assertTrue($audits->contains(fn ($log): bool => $log->action === 'attendance_mark.updated'));
        $this->assertStringNotContainsString($note, $audits->toJson());
        $this->assertSame('justified', $this->tenant(fn (): string => AttendanceMark::query()->where('student_id', $this->students[0])->sole()->status));
    }

    public function test_outra_escola_nao_pode_consultar_nem_gravar_e_professor_da_escola_pode(): void
    {
        $this->login('teacher', [self::ESCOLA_A2]);
        $this->get($this->url())->assertForbidden();
        $this->post($this->url(), $this->payload())->assertForbidden();
        $this->assertSame(0, $this->tenant(fn (): int => AttendanceSession::query()->count()));

        $this->login('teacher', [self::ESCOLA_A1]);
        $this->get($this->url())->assertOk();
        $this->get($this->url(self::ESCOLA_A2))->assertForbidden();
    }

    public function test_data_futura_e_aluno_de_outra_turma_sao_recusados(): void
    {
        $this->login('school_secretary');
        $this->post($this->url(), $this->payload(date: '2027-04-16'))->assertSessionHasErrors('data');
        $this->get($this->url().'?data=2027-04-16')->assertSessionHasErrors('data');

        $otherClassId = $this->tenant(fn (): string => SchoolClass::query()->create([
            'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'school_year_id' => $this->yearId,
            'name' => '3º B', 'stage' => 'Ensino Fundamental', 'modality' => 'Regular',
            'shift' => 'Tarde', 'status' => 'active',
        ])->id);
        $otherStudent = $this->student('Aluno de outra turma fictício', classId: $otherClassId);
        $data = $this->payload();
        $data['presencas'][$otherStudent] = ['situacao' => 'present'];
        $this->post($this->url(), $data)->assertSessionHasErrors('presencas');
        $this->assertSame(0, $this->tenant(fn (): int => AttendanceSession::query()->count()));
        $this->get($this->url())->assertDontSee('Aluno de outra turma fictício');
    }

    public function test_aluno_sem_matricula_ativa_fica_fora_e_nao_aceita_marca_falsa(): void
    {
        $inactive = $this->student('Transferido fictício', status: 'transferred');
        $this->login('teacher');
        $this->get($this->url())->assertDontSee('Transferido fictício');
        $data = $this->payload();
        $data['presencas'][$inactive] = ['situacao' => 'absent'];
        $this->post($this->url(), $data)->assertSessionHasErrors('presencas');
        $this->assertSame(0, $this->tenant(fn (): int => AttendanceSession::query()->count()));
    }

    public function test_correcao_passada_exige_gestao_motivo_e_segundo_fator_recente(): void
    {
        $this->seedAttendance('2027-04-14', array_fill_keys($this->students, 'present'));
        $this->login('teacher');
        $past = $this->payload('2027-04-14', [$this->students[0] => 'absent']);
        $this->post($this->url().'/corrigir', [...$past, 'motivo' => 'Revisão administrativa fictícia de presença.'])
            ->assertForbidden();
        $this->post($this->url(), $past)->assertSessionHasErrors('data');

        $this->login('school_secretary');
        $this->get($this->url().'?data=2027-04-14')->assertOk()->assertSee('Motivo da correção');
        $this->post($this->url().'/corrigir', $past)->assertSessionHasErrors('motivo');
        $this->post($this->url().'/corrigir', [...$past, 'motivo' => 'curto'])->assertSessionHasErrors('motivo');

        $this->travel(16)->minutes();
        $this->from($this->url().'?data=2027-04-14')->post($this->url().'/corrigir', [
            ...$past, 'motivo' => 'Conferência administrativa realizada depois da chamada.',
        ])->assertRedirect('/seguranca/segundo-fator/desafio');
        $this->assertSame('present', $this->tenant(fn (): string => AttendanceMark::query()
            ->where('student_id', $this->students[0])->sole()->status));

        $this->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp()),
        ])->assertRedirect();
        $this->get($this->url().'?data=2027-04-14')->assertOk()
            ->assertSee('Conferência administrativa realizada depois da chamada.');
        $this->post($this->url().'/corrigir', [
            ...$past, 'motivo' => 'Conferência administrativa realizada depois da chamada.',
        ])->assertRedirect();
        $this->assertSame('absent', $this->tenant(fn (): string => AttendanceMark::query()
            ->where('student_id', $this->students[0])->sole()->status));
        $audit = $this->tenant(fn () => AuditLog::query()->where('action', 'attendance_session.corrected')->sole());
        $this->assertSame('[REDACTED]', $audit->after['correction_reason']);
    }

    public function test_data_passada_sem_chamada_nao_cria_registro_retroativo(): void
    {
        $this->login('school_director');
        $this->post($this->url().'/corrigir', [
            ...$this->payload('2027-04-14'), 'motivo' => 'Correção de chamada que ainda não existe.',
        ])->assertSessionHasErrors('data');
        $this->assertSame(0, $this->tenant(fn (): int => AttendanceSession::query()->count()));
    }

    public function test_calcula_percentual_no_mes_alerta_de_tres_faltas_e_sem_registros(): void
    {
        $third = $this->student('Sem chamadas fictício');
        $days = ['2027-04-03', '2027-04-04', '2027-04-05', '2027-04-06'];
        foreach ($days as $i => $day) {
            $this->seedAttendance($day, [
                $this->students[0] => $i < 2 ? 'present' : 'absent',
                $this->students[1] => $i === 0 ? 'present' : 'justified',
            ]);
        }
        $this->login('teacher');

        $response = $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/turmas/'.$this->classId)->assertOk();
        $response->assertSee('Frequência no mês')->assertSee('50%')
            ->assertSee('Abaixo de 75%')->assertSee('Atenção: 3 faltas seguidas')
            ->assertSee('Sem chamadas');
        $this->assertSame(0, $this->tenant(fn (): int => AttendanceMark::query()->where('student_id', $third)->count()));
    }

    public function test_segundo_fator_nao_confirmado_impede_gravacao(): void
    {
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, 'teacher', [self::ESCOLA_A1]);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post($this->url(), $this->payload())
            ->assertRedirect('/seguranca/segundo-fator/desafio');
        $this->assertSame(0, $this->tenant(fn (): int => AttendanceSession::query()->count()));
    }

    public function test_banco_impede_sessao_duplicada_para_mesma_turma_e_data(): void
    {
        $this->seedAttendance('2027-04-14', array_fill_keys($this->students, 'present'));
        $this->expectException(QueryException::class);
        $this->tenant(fn () => AttendanceSession::query()->create([
            'id' => (string) Str::uuid(), 'school_class_id' => $this->classId,
            'attendance_date' => '2027-04-14', 'recorded_by' => $this->auditFixtureUser->id,
            'recorded_at' => now(),
        ]));
    }

    public function test_banco_recusa_situacao_de_presenca_fora_da_lista(): void
    {
        $this->seedAttendance('2027-04-14', array_fill_keys($this->students, 'present'));
        $sessionId = $this->tenant(fn (): string => AttendanceSession::query()->sole()->id);
        $extra = $this->student('Terceiro aluno fictício');
        $this->expectException(QueryException::class);
        $this->tenant(fn () => AttendanceMark::query()->create([
            'id' => (string) Str::uuid(), 'attendance_session_id' => $sessionId,
            'student_id' => $extra, 'status' => 'unknown',
        ]));
    }

    public function test_frequencia_na_turma_nao_consulta_por_aluno(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->students[] = $this->student('Fictício '.$i);
        }
        $this->seedAttendance('2027-04-13', array_fill_keys($this->students, 'present'));
        $this->login('school_secretary');
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            if ($query->connectionName === 'tenant') {
                $queries[] = $query->sql;
            }
        });
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/turmas/'.$this->classId)->assertOk();
        $attendanceQueries = array_filter($queries, function (string $sql): bool {
            return preg_match('/from\\s+"?attendance_(?:sessions|marks)"?/i', $sql) === 1;
        });
        $this->assertLessThanOrEqual(4, count($attendanceQueries));
    }
}
