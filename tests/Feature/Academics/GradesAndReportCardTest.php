<?php

namespace Tests\Feature\Academics;

use App\Models\Tenant\AcademicPeriod;
use App\Models\Tenant\AttendanceMark;
use App\Models\Tenant\AttendanceSession;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\ClassTeacherAssignment;
use App\Models\Tenant\CurricularComponent;
use App\Models\Tenant\GradeEntry;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\User;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class GradesAndReportCardTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private string $yearId;

    private string $classId;

    private string $otherClassId;

    private string $infantClassId;

    private string $studentId;

    private string $enrollmentId;

    private string $secondEnrollmentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2027-04-15 09:00:00'));

        [$this->yearId, $this->classId, $this->otherClassId, $this->infantClassId,
            $this->studentId, $this->enrollmentId, $this->secondEnrollmentId] = $this->tenant(function (): array {
                $year = SchoolYear::query()->create([
                    'id' => (string) Str::uuid(), 'year' => 2027,
                    'start_date' => '2027-02-01', 'end_date' => '2027-12-18', 'status' => 'active',
                ]);
                $fundamental = SchoolClass::query()->create([
                    'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id,
                    'name' => '3º Ano A', 'stage' => 'Ensino Fundamental', 'modality' => 'Regular',
                    'shift' => 'Manhã', 'status' => 'active',
                ]);
                $other = SchoolClass::query()->create([
                    'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id,
                    'name' => '4º Ano A', 'stage' => 'Ensino Fundamental', 'modality' => 'Regular',
                    'shift' => 'Tarde', 'status' => 'active',
                ]);
                $infant = SchoolClass::query()->create([
                    'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A2, 'school_year_id' => $year->id,
                    'name' => 'Maternal I', 'stage' => 'Educação Infantil', 'modality' => 'Regular',
                    'shift' => 'Manhã', 'status' => 'active',
                ]);

                $student = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => 'Alice Estudante Fictícia']);
                $second = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => 'Bruno Estudante Fictício']);
                $enrollment = StudentEnrollment::query()->create([
                    'id' => (string) Str::uuid(), 'student_id' => $student->id,
                    'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id,
                    'school_class_id' => $fundamental->id, 'status' => 'active',
                ]);
                $secondEnrollment = StudentEnrollment::query()->create([
                    'id' => (string) Str::uuid(), 'student_id' => $second->id,
                    'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id,
                    'school_class_id' => $fundamental->id, 'status' => 'active',
                ]);

                $staff = StaffMember::query()->create([
                    'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1,
                    'full_name' => 'Professora de Teste Fictícia', 'role' => 'teacher',
                    'institutional_email' => 'professora@escola.invalid', 'status' => 'active',
                ]);
                ClassTeacherAssignment::query()->create([
                    'id' => (string) Str::uuid(), 'school_class_id' => $fundamental->id,
                    'staff_member_id' => $staff->id, 'subject' => 'Matemática',
                    'is_main' => true, 'starts_on' => '2027-02-01',
                ]);

                return [
                    $year->id, $fundamental->id, $other->id, $infant->id,
                    $student->id, $enrollment->id, $secondEnrollment->id,
                ];
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

    private function login(string $role, array $schools = [self::ESCOLA_A1], ?string $email = null): User
    {
        $user = $this->criarUsuario($email ?? (string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schools);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp()),
        ])->assertRedirect();

        return $user;
    }

    private function gradeUrl(?string $class = null, string $school = self::ESCOLA_A1): string
    {
        return '/m/municipio-a/escolas/'.$school.'/turmas/'.($class ?? $this->classId).'/notas';
    }

    private function reportUrl(string $studentId, string $school = self::ESCOLA_A1): string
    {
        return '/m/municipio-a/escolas/'.$school.'/alunos/'.$studentId.'/boletim';
    }

    private function periodId(int $number = 1): string
    {
        return $this->tenant(fn (): string => (string) AcademicPeriod::query()
            ->where('school_year_id', $this->yearId)->where('number', $number)->sole()->id);
    }

    private function componentId(string $code = 'matematica'): string
    {
        return $this->tenant(fn (): string => (string) CurricularComponent::query()->where('code', $code)->sole()->id);
    }

    private function payload(array $values = []): array
    {
        return [
            'periodo' => $this->periodId(),
            'componente' => $this->componentId(),
            'grades' => [
                $this->enrollmentId => ['grade' => '8,5', 'absences' => '2'],
                $this->secondEnrollmentId => ['grade' => '7.0', 'absences' => ''],
                ...$values,
            ],
        ];
    }

    public function test_ano_tem_quatro_bimestres_e_oito_componentes_iniciais(): void
    {
        $this->tenant(function (): void {
            $periods = AcademicPeriod::query()->where('school_year_id', $this->yearId)->orderBy('number')->get();
            $this->assertCount(4, $periods);
            $this->assertSame([1, 2, 3, 4], $periods->pluck('number')->all());
            $this->assertTrue($periods->every(fn (AcademicPeriod $item): bool => $item->kind === 'bimestre'));
            $this->assertSame('2027-02-01', $periods->first()->starts_on->format('Y-m-d'));
            $this->assertSame('2027-12-18', $periods->last()->ends_on->format('Y-m-d'));
            $this->assertSame(8, CurricularComponent::query()->count());
            $english = CurricularComponent::query()->where('code', 'lingua-inglesa')->sole();
            $this->assertTrue($english->is_optional);
        });
    }

    public function test_lancamento_em_lote_aceita_virgula_ponto_e_audita_apenas_alteracoes(): void
    {
        $this->login('school_secretary');
        $this->get($this->gradeUrl())->assertOk()->assertSee('Notas da turma')
            ->assertSee('Alice Estudante Fictícia')->assertSee('Bruno Estudante Fictício');
        $this->post($this->gradeUrl(), $this->payload())->assertRedirect();

        $results = $this->tenant(fn (): array => [
            GradeEntry::query()->orderBy('grade')->pluck('grade')->all(),
            GradeEntry::query()->where('student_enrollment_id', $this->enrollmentId)->sole()->absences,
            AuditLog::query()->where('action', 'academic.grade.created')->count(),
        ]);
        $this->assertSame(['7.0', '8.5'], $results[0]);
        $this->assertSame(2, $results[1]);
        $this->assertSame(2, $results[2]);

        $this->post($this->gradeUrl(), $this->payload())->assertRedirect();
        $this->assertSame(2, $this->tenant(fn (): int => AuditLog::query()->where('action', 'academic.grade.created')->count()));
        $this->assertSame(0, $this->tenant(fn (): int => AuditLog::query()->where('action', 'academic.grade.updated')->count()));

        $changed = $this->payload();
        $changed['grades'][$this->enrollmentId]['grade'] = '9,5';
        $this->post($this->gradeUrl(), $changed)->assertRedirect();
        $this->tenant(function (): void {
            $audit = AuditLog::query()->where('action', 'academic.grade.updated')->sole();
            $this->assertSame('8.5', $audit->before['grade']);
            $this->assertSame('9.5', $audit->after['grade']);
            $this->assertSame(2, $audit->after['absences']);
        });

        $invalid = $this->payload();
        $invalid['grades'][$this->enrollmentId]['grade'] = '10,5';
        $this->post($this->gradeUrl(), $invalid)
            ->assertSessionHasErrors('grades.'.$this->enrollmentId.'.grade');
    }

    public function test_professor_precisa_vinculo_teacher_e_email_do_servidor_atribuido(): void
    {
        $this->login('teacher', [self::ESCOLA_A1], 'professora@escola.invalid');
        $this->get($this->gradeUrl())->assertOk()->assertSee('Salvar alterações');
        $this->post($this->gradeUrl(), $this->payload())->assertRedirect();
        $this->get($this->gradeUrl($this->otherClassId))->assertOk()->assertSee('mesmo e-mail institucional');
        $this->post($this->gradeUrl($this->otherClassId), $this->payload())->assertForbidden();

        $this->login('teacher', [self::ESCOLA_A1], 'outra@escola.invalid');
        $this->get($this->gradeUrl())->assertOk()->assertSee('mesmo e-mail institucional');
        $this->post($this->gradeUrl(), $this->payload())->assertForbidden();
        $this->post($this->gradeUrl($this->otherClassId), $this->payload())->assertForbidden();

        $this->login('teacher', [self::ESCOLA_A2], 'terceira@escola.invalid');
        $this->get($this->gradeUrl())->assertForbidden();
        $this->post($this->gradeUrl(), $this->payload())->assertForbidden();
    }

    public function test_direcao_e_gestao_podem_lancar_e_turma_infantil_nao_tem_notas(): void
    {
        $this->login('school_director');
        $this->post($this->gradeUrl(), $this->payload())->assertRedirect();

        $this->get($this->gradeUrl($this->infantClassId, self::ESCOLA_A2))->assertForbidden();

        $this->login('municipal_manager', []);
        $this->get($this->gradeUrl($this->infantClassId, self::ESCOLA_A2))->assertNotFound();

        $infant = $this->tenant(function (): string {
            $student = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => 'Criança Infantil Fictícia']);
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A2,
                'student_id' => $student->id, 'school_year_id' => $this->yearId,
                'school_class_id' => $this->infantClassId, 'status' => 'active',
            ]);

            return $student->id;
        });
        $this->get($this->reportUrl($infant, self::ESCOLA_A2))->assertNotFound();
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A2.'/alunos/'.$infant)->assertOk()
            ->assertDontSee('Abrir boletim');
    }

    public function test_boletim_media_frequencia_anual_e_periodos_abertos(): void
    {
        $this->tenant(function (): void {
            $math = CurricularComponent::query()->where('code', 'matematica')->sole();
            $science = CurricularComponent::query()->where('code', 'ciencias')->sole();
            $periods = AcademicPeriod::query()->where('school_year_id', $this->yearId)
                ->orderBy('number')->get();

            foreach ([
                [$math->id, $periods[0]->id, '8.0'],
                [$math->id, $periods[1]->id, '4.0'],
                [$science->id, $periods[0]->id, '5.0'],
            ] as [$component, $period, $grade]) {
                GradeEntry::query()->create([
                    'id' => (string) Str::uuid(), 'student_enrollment_id' => $this->enrollmentId,
                    'school_class_id' => $this->classId, 'academic_period_id' => $period,
                    'curricular_component_id' => $component, 'grade' => $grade,
                    'recorded_by' => $this->auditFixtureUser->id,
                    'updated_by' => $this->auditFixtureUser->id,
                ]);
            }

            foreach (['2027-04-01' => 'present', '2027-04-02' => 'absent', '2027-04-03' => 'justified'] as $day => $status) {
                $session = AttendanceSession::query()->create([
                    'id' => (string) Str::uuid(), 'school_class_id' => $this->classId,
                    'attendance_date' => $day, 'recorded_by' => $this->auditFixtureUser->id,
                    'recorded_at' => now(),
                ]);
                AttendanceMark::query()->create([
                    'id' => (string) Str::uuid(), 'attendance_session_id' => $session->id,
                    'student_id' => $this->studentId, 'status' => $status,
                ]);
            }
        });

        $this->login('school_secretary');
        $response = $this->get($this->reportUrl($this->studentId))->assertOk()
            ->assertSee('Boletim parcial')
            ->assertSee('33,3%')
            ->assertSee('6,0')
            ->assertSee('Abaixo da média')
            ->assertSee('Períodos ainda abertos')
            ->assertSee('boletim-imprimir.js');

        $view = $response->viewData('data');
        $mathRow = $view['rows']->first(fn ($row) => $row['component']->code === 'matematica');
        $this->assertSame(6.0, $mathRow['mean']);
        $this->assertSame(33.3, $view['frequency']);
        $this->assertCount(4, $view['openPeriods']);

        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$this->studentId)
            ->assertOk()->assertSee('Abrir boletim');

        $this->login('school_secretary', [self::ESCOLA_A2]);
        $this->get($this->reportUrl($this->studentId))->assertForbidden();
    }

    public function test_fechar_periodo_exige_2fa_recente_e_professor_nao_corrige(): void
    {
        $periodId = $this->periodId();
        $url = '/m/municipio-a/avaliacoes/anos/'.$this->yearId.'/periodos/'.$periodId.'/fechar';
        $this->login('municipal_manager', []);

        $this->post($url, ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post($url, ['reason' => 'Lançamentos do período conferidos em teste.'])->assertRedirect();

        $this->tenant(function () use ($periodId): void {
            $this->assertNotNull(AcademicPeriod::query()->findOrFail($periodId)->closed_at);
            $this->assertSame(1, AuditLog::query()->where('action', 'academic.period.closed')->count());
        });

        $this->login('teacher', [self::ESCOLA_A1], 'professora@escola.invalid');
        $this->get($this->gradeUrl())->assertOk()->assertSee('professores só consultam');
        $this->post($this->gradeUrl(), $this->payload())->assertSessionHasErrors('periodo');
        $this->post($this->gradeUrl().'/corrigir', $this->payload() + ['reason' => 'Correção de teste.'])
            ->assertForbidden();

        $manager = $this->login('school_director');
        $this->travelTo(now()->addMinutes(20));
        $change = $this->payload() + ['reason' => 'Correção com justificativa após fechamento.'];
        $this->post($this->gradeUrl().'/corrigir', $change)
            ->assertRedirect(route('two-factor.challenge'));

        $this->actingAs($manager)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp()),
        ])->assertRedirect();
        $this->post($this->gradeUrl().'/corrigir', $change)->assertRedirect();
        $this->tenant(function (): void {
            $entry = GradeEntry::query()->where('student_enrollment_id', $this->enrollmentId)->sole();
            $this->assertSame('8.5', $entry->grade);
            $audit = AuditLog::query()->where('action', 'academic.grade.created')->firstOrFail();
            $this->assertSame('Correção com justificativa após fechamento.', $audit->after['change_reason']);
        });
    }

    public function test_municipio_define_trimestres_componentes_e_periodo_fechado_nao_muda(): void
    {
        $this->login('school_secretary');
        $config = '/m/municipio-a/avaliacoes/anos/'.$this->yearId.'/periodos';
        $this->get($config)->assertForbidden();

        $this->login('municipal_manager', []);
        $this->get($config)->assertOk()->assertSee('Quatro bimestres');
        $this->get($config.'?formato=trimestre')->assertOk()->assertSee('Três trimestres');

        $this->post($config, [
            'kind' => 'trimestre',
            'periods' => [
                1 => ['starts_on' => '2027-02-01', 'ends_on' => '2027-05-31'],
                2 => ['starts_on' => '2027-06-01', 'ends_on' => '2027-09-30'],
                3 => ['starts_on' => '2027-10-01', 'ends_on' => '2027-12-18'],
            ],
        ])->assertRedirect();
        $this->tenant(function (): void {
            $periods = AcademicPeriod::query()->where('school_year_id', $this->yearId)->orderBy('number')->get();
            $this->assertCount(3, $periods);
            $this->assertTrue($periods->every(fn (AcademicPeriod $item): bool => $item->kind === 'trimestre'));
        });

        $this->get('/m/municipio-a/avaliacoes/componentes')->assertOk()->assertSee('Língua Portuguesa')
            ->assertSee('Língua Inglesa');

        $id = $this->periodId();
        $this->post($config.'/'.$id.'/fechar', ['reason' => 'Trimestre concluído em teste.'])->assertRedirect();
        $this->post($config, [
            'kind' => 'trimestre',
            'periods' => [
                1 => ['starts_on' => '2027-02-02', 'ends_on' => '2027-05-31'],
                2 => ['starts_on' => '2027-06-01', 'ends_on' => '2027-09-30'],
                3 => ['starts_on' => '2027-10-01', 'ends_on' => '2027-12-18'],
            ],
        ])->assertSessionHasErrors('periods');
    }
}
