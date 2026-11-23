<?php

namespace Tests\Feature\Academics;

use App\Models\Tenant\AcademicPeriod;
use App\Models\Tenant\AttendanceMark;
use App\Models\Tenant\AttendanceSession;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\ClassTeacherAssignment;
use App\Models\Tenant\ClassYearClosure;
use App\Models\Tenant\CurricularComponent;
use App\Models\Tenant\GradeEntry;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentFinalResult;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class YearEndResultsTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private string $yearId;

    private string $classId;

    private string $infantClassId;

    /** @var array<string, string> */
    private array $enrollments = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2027-12-18 10:00:00'));

        [$this->yearId, $this->classId, $this->infantClassId, $this->enrollments] =
            $this->tenant(function (): array {
                $year = SchoolYear::query()->create([
                    'id' => (string) Str::uuid(), 'year' => 2027,
                    'start_date' => '2027-02-01', 'end_date' => '2027-12-18', 'status' => 'active',
                ]);
                $class = SchoolClass::query()->create([
                    'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1,
                    'school_year_id' => $year->id, 'name' => '5º A',
                    'stage' => 'Ensino Fundamental', 'modality' => 'Regular',
                    'shift' => 'Manhã', 'status' => 'active',
                ]);
                $infant = SchoolClass::query()->create([
                    'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A2,
                    'school_year_id' => $year->id, 'name' => 'Pré I',
                    'stage' => 'Educação Infantil', 'modality' => 'Regular',
                    'shift' => 'Manhã', 'status' => 'active',
                ]);
                $enrollments = [];
                foreach ([
                    'alice' => ['Ana Fictícia', 'active'],
                    'bruno' => ['Bruno Fictício', 'active'],
                    'carla' => ['Carla Fictícia', 'active'],
                    'saida' => ['Denis Fictício', 'transferred'],
                ] as $key => [$name, $status]) {
                    $student = Student::query()->create([
                        'id' => (string) Str::uuid(), 'full_name' => $name,
                    ]);
                    $enrollments[$key] = StudentEnrollment::query()->create([
                        'id' => (string) Str::uuid(), 'student_id' => $student->id,
                        'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id,
                        'school_class_id' => $class->id, 'status' => $status,
                    ])->id;
                }

                $components = CurricularComponent::query()
                    ->where('active', true)->where('is_optional', false)->get();
                $first = AcademicPeriod::query()->where('school_year_id', $year->id)
                    ->orderBy('number')->firstOrFail();
                foreach ($components as $component) {
                    foreach (['alice', 'bruno'] as $key) {
                        GradeEntry::query()->create([
                            'id' => (string) Str::uuid(),
                            'school_class_id' => $class->id,
                            'student_enrollment_id' => $enrollments[$key],
                            'academic_period_id' => $first->id,
                            'curricular_component_id' => $component->id,
                            'grade' => $key === 'bruno' && $component->code === 'matematica' ? '4.0' : '7.0',
                            'recorded_by' => $this->auditFixtureUser->id,
                            'updated_by' => $this->auditFixtureUser->id,
                        ]);
                    }
                }

                foreach (range(1, 4) as $day) {
                    $session = AttendanceSession::query()->create([
                        'id' => (string) Str::uuid(), 'school_class_id' => $class->id,
                        'attendance_date' => '2027-10-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT),
                        'recorded_by' => $this->auditFixtureUser->id,
                        'recorded_at' => now(),
                    ]);
                    foreach (['alice', 'bruno'] as $key) {
                        $studentId = StudentEnrollment::query()->findOrFail($enrollments[$key])->student_id;
                        AttendanceMark::query()->create([
                            'id' => (string) Str::uuid(), 'attendance_session_id' => $session->id,
                            'student_id' => $studentId,
                            'status' => $key === 'bruno' && $day > 2 ? 'absent' : 'present',
                        ]);
                    }
                }

                $staff = StaffMember::query()->create([
                    'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1,
                    'full_name' => 'Professora Fictícia', 'role' => 'teacher',
                    'status' => 'active', 'institutional_email' => 'professora@example.invalid',
                ]);
                ClassTeacherAssignment::query()->create([
                    'id' => (string) Str::uuid(), 'school_class_id' => $class->id,
                    'staff_member_id' => $staff->id, 'subject' => 'Matemática',
                    'is_main' => true, 'starts_on' => '2027-02-01',
                ]);

                return [$year->id, $class->id, $infant->id, $enrollments];
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

    private function login(string $role, array $schools = [self::ESCOLA_A1], ?string $email = null): void
    {
        $user = $this->criarUsuario($email ?? Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schools);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp()),
        ])->assertRedirect();
    }

    private function base(?string $class = null, string $school = self::ESCOLA_A1): string
    {
        return '/m/municipio-a/escolas/'.$school.'/turmas/'.($class ?? $this->classId);
    }

    private function confirmAll(): void
    {
        $this->post($this->base().'/resultado-final/confirmar', ['all' => '1'])->assertRedirect();
    }

    private function closePeriods(): void
    {
        $this->tenant(function (): void {
            AcademicPeriod::query()->where('school_year_id', $this->yearId)
                ->get()->each(fn (AcademicPeriod $period) => $period->forceFill([
                    'closed_at' => now(), 'closed_by' => $this->auditFixtureUser->id,
                    'close_reason' => 'Período fictício encerrado por conferência.',
                ])->save());
        });
    }

    public function test_sugestoes_usam_medias_frequencia_e_nunca_gravam_decisao_automatica(): void
    {
        $this->login('school_secretary');
        $response = $this->get($this->base().'/resultado-final')->assertOk()
            ->assertSee('Ana Fictícia')->assertSee('Bruno Fictício')
            ->assertSee('Carla Fictícia')->assertSee('Denis Fictício')
            ->assertSee('50,0%')->assertSee('Frequência')
            ->assertSee('Transferido')->assertSee('Confirmar todas as sugestões pendentes');

        $rows = $response->viewData('data')['records']->keyBy('enrollment.id');
        $this->assertSame('aprovado', $rows[$this->enrollments['alice']]['suggestion']);
        $this->assertSame('retido', $rows[$this->enrollments['bruno']]['suggestion']);
        $this->assertSame('cursando', $rows[$this->enrollments['carla']]['suggestion']);
        $this->assertNull($rows[$this->enrollments['saida']]['suggestion']);
        $this->assertStringContainsString('Matemática', $rows[$this->enrollments['bruno']]['reason']);
        $this->assertStringContainsString('50,0%', $rows[$this->enrollments['bruno']]['reason']);
        $this->assertSame(0, $this->tenant(fn (): int => StudentFinalResult::query()->count()));

        $this->get($this->base().'/ata-final')->assertOk()
            ->assertSee('PRÉVIA NÃO OFICIAL')
            ->assertSee('Denis Fictício')->assertSee('Transferido')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_confirmacao_manual_lote_e_override_exigem_motivo_e_auditoria(): void
    {
        $this->login('school_director');
        $url = $this->base().'/resultado-final/confirmar';
        $this->post($url, [
            'enrollment' => $this->enrollments['alice'], 'result' => 'retido',
        ])->assertSessionHasErrors('reason');

        $this->post($url, [
            'enrollment' => $this->enrollments['alice'], 'result' => 'retido',
            'reason' => 'Decisão de conselho de classe fictício.',
        ])->assertRedirect();
        $this->confirmAll();

        $this->tenant(function (): void {
            $this->assertSame(3, StudentFinalResult::query()->count());
            $override = StudentFinalResult::query()
                ->where('student_enrollment_id', $this->enrollments['alice'])->sole();
            $this->assertSame('retido', $override->result);
            $this->assertSame('aprovado', $override->suggested_result);
            $this->assertSame('Decisão de conselho de classe fictício.', $override->override_reason);
            $this->assertSame(3, AuditLog::query()->where('action', 'academic.final_result.confirmed')->count());
            $audit = AuditLog::query()->where('auditable_id', $override->id)
                ->where('action', 'academic.final_result.confirmed')->sole();
            $this->assertSame('retido', $audit->after['result']);
            $this->assertSame('aprovado', $audit->after['suggested_result']);
            $this->assertStringNotContainsString('Ana Fictícia', $audit->toJson());
        });

        $this->confirmAll();
        $this->assertSame(3, $this->tenant(fn (): int => StudentFinalResult::query()->count()));
    }

    public function test_fechamento_recusa_periodos_abertos_e_trava_alteracoes_no_banco_ate_reabertura(): void
    {
        $this->login('school_secretary');
        $url = $this->base().'/resultado-final/fechar';
        $this->confirmAll();
        $this->post($url, ['reason' => 'Encerramento após conferência integral da turma.'])
            ->assertSessionHasErrors('reason');

        $this->closePeriods();
        $this->post($url, ['reason' => 'Encerramento após conferência integral da turma.'])
            ->assertRedirect();
        $this->tenant(function (): void {
            $closure = ClassYearClosure::query()->findOrFail($this->classId);
            $this->assertNotNull($closure->closed_at);
            $this->assertSame(1, AuditLog::query()->where('action', 'academic.year.closed')->count());
        });

        $this->post($this->base().'/resultado-final/confirmar', ['all' => '1'])
            ->assertSessionHasErrors('result');

        $this->tenant(function (): void {
            $entry = GradeEntry::query()->where('student_enrollment_id', $this->enrollments['alice'])->firstOrFail();
            try {
                DB::connection('tenant')->table('grade_entries')->where('id', $entry->id)->update(['grade' => '1.0']);
                $this->fail('PostgreSQL deveria impedir atualização de notas após fechamento.');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('Ano letivo da turma fechado', $exception->getMessage());
            }
        });

        $this->login('school_director');
        $this->post($this->base().'/resultado-final/reabrir', ['reason' => 'Justificativa fictícia para reabertura.'])
            ->assertForbidden();

        $this->login('municipal_manager', []);
        $this->post($this->base().'/resultado-final/reabrir', ['reason' => 'Revisão excepcional autorizada pela rede.'])
            ->assertRedirect();
        $this->tenant(function (): void {
            $this->assertNull(ClassYearClosure::query()->findOrFail($this->classId)->closed_at);
            $this->assertSame(1, AuditLog::query()->where('action', 'academic.year.reopened')->count());
            $entry = GradeEntry::query()->where('student_enrollment_id', $this->enrollments['alice'])->firstOrFail();
            DB::connection('tenant')->table('grade_entries')->where('id', $entry->id)->update(['grade' => '9.0']);
            $this->assertSame('9.0', GradeEntry::query()->findOrFail($entry->id)->grade);
        });
    }

    public function test_professor_so_consulta_ata_da_turma_atribuida_e_infantil_nao_tem_fechamento(): void
    {
        $this->login('teacher', [self::ESCOLA_A1], 'professora@example.invalid');
        $this->get($this->base().'/ata-final')->assertOk()
            ->assertSee('Ata de resultados finais');
        $this->get($this->base().'/resultado-final')->assertForbidden();
        $this->post($this->base().'/resultado-final/confirmar', ['all' => '1'])->assertForbidden();
        $this->post($this->base().'/resultado-final/fechar', ['reason' => 'Encerramento fictício de aluno.'])
            ->assertForbidden();

        $this->login('teacher', [self::ESCOLA_A1], 'outro@example.invalid');
        $this->get($this->base().'/ata-final')->assertForbidden();

        $this->login('school_secretary', [self::ESCOLA_A2]);
        $this->get($this->base().'/ata-final')->assertForbidden();
        $this->get($this->base().'/resultado-final')->assertForbidden();
        $this->get($this->base($this->infantClassId, self::ESCOLA_A2).'/ata-final')->assertNotFound();
        $this->get($this->base($this->infantClassId, self::ESCOLA_A2).'/resultado-final')->assertNotFound();
    }

    public function test_segundo_fator_recente_e_gestao_municipal_nao_confirmam_resultados(): void
    {
        $this->login('municipal_manager', []);
        $this->get($this->base().'/resultado-final')->assertOk();
        $this->post($this->base().'/resultado-final/confirmar', ['all' => '1'])->assertForbidden();

        $this->login('school_secretary');
        $this->closePeriods();
        $this->confirmAll();
        $this->travelTo(now()->addMinutes(20));
        $this->post($this->base().'/resultado-final/fechar', [
            'reason' => 'Fechamento com código recente do segundo fator.',
        ])->assertRedirect(route('two-factor.challenge'));
        $this->assertNull($this->tenant(fn (): mixed => ClassYearClosure::query()->findOrFail($this->classId)->closed_at));
    }
}
