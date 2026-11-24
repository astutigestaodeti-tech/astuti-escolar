<?php

namespace Tests\Feature\Academics;

use App\Academics\CouncilRoster;
use App\Academics\PedagogicalPanel;
use App\Models\Tenant\AcademicPeriod;
use App\Models\Tenant\AttendanceMark;
use App\Models\Tenant\AttendanceSession;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\ClassCouncil;
use App\Models\Tenant\ClassCouncilStudent;
use App\Models\Tenant\ClassTeacherAssignment;
use App\Models\Tenant\CurricularComponent;
use App\Models\Tenant\GradeEntry;
use App\Models\Tenant\LiteracyAssessment;
use App\Models\Tenant\MultiprofessionalRequest;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolClassCensus;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class PedagogicalTest extends TwoMunicipalityTestCase
{
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-04-15 10:00:00'));
        $this->ids = $this->tenant(function (): array {
            $year = SchoolYear::query()->create(['id' => Str::uuid()->toString(), 'year' => 2026, 'start_date' => '2026-02-02', 'end_date' => '2026-12-18', 'status' => 'active']);
            $period = AcademicPeriod::query()->create(['id' => Str::uuid()->toString(), 'school_year_id' => $year->id, 'kind' => 'bimestre', 'number' => 1, 'starts_on' => '2026-02-02', 'ends_on' => '2026-04-30']);
            [$class, $enrollments] = $this->createClass($year->id, 1);
            $teacher = StaffMember::query()->create(['id' => Str::uuid()->toString(), 'school_id' => self::ESCOLA_A1, 'role' => 'teacher', 'status' => 'active', 'full_name' => 'Docente Fictício', 'institutional_email' => 'docente-council@example.invalid']);
            ClassTeacherAssignment::query()->create(['id' => Str::uuid()->toString(), 'staff_member_id' => $teacher->id, 'school_class_id' => $class->id, 'starts_on' => '2026-02-02', 'is_main' => true]);
            $component = CurricularComponent::query()->create(['id' => Str::uuid()->toString(), 'code' => 'CP'.Str::upper(Str::random(8)), 'name' => 'Componente Fictício', 'active' => true, 'display_order' => 1]);
            $entry = $enrollments[0];
            GradeEntry::query()->create(['id' => Str::uuid()->toString(), 'student_enrollment_id' => $entry->id, 'school_class_id' => $class->id, 'academic_period_id' => $period->id, 'curricular_component_id' => $component->id, 'grade' => 8.5, 'recorded_by' => $this->auditFixtureUser->id, 'updated_by' => $this->auditFixtureUser->id]);
            $session = AttendanceSession::query()->create(['id' => Str::uuid()->toString(), 'school_class_id' => $class->id, 'attendance_date' => '2026-02-03', 'recorded_by' => $this->auditFixtureUser->id, 'recorded_at' => now()]);
            AttendanceMark::query()->create(['id' => Str::uuid()->toString(), 'attendance_session_id' => $session->id, 'student_id' => $entry->student_id, 'status' => 'justified']);
            LiteracyAssessment::query()->create(['id' => Str::uuid()->toString(), 'school_class_id' => $class->id, 'student_enrollment_id' => $entry->id, 'term' => 1, 'writing' => 'alphabetic', 'reading' => 'words', 'recorded_by' => $this->auditFixtureUser->id, 'updated_by' => $this->auditFixtureUser->id]);

            return ['year' => $year->id, 'period' => $period->id, 'class' => $class->id, 'enrollment' => $entry->id, 'student' => $entry->student_id];
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

    private function createClass(string $year, int $n): array
    {
        $class = SchoolClass::query()->create(['id' => Str::uuid()->toString(), 'school_id' => self::ESCOLA_A1, 'school_year_id' => $year, 'name' => '1º Ano Fictício '.$n, 'stage' => 'Ensino Fundamental', 'modality' => 'Regular', 'shift' => 'Manhã', 'status' => 'active']);
        SchoolClassCensus::query()->create(['school_class_id' => $class->id, 'inep_stage_code' => 14, 'mediation' => 1, 'class_type' => 6, 'schedule_monday' => '07:00-11:00']);
        $entries = [];
        foreach (range(1, 2) as $i) {
            $student = Student::query()->create(['id' => Str::uuid()->toString(), 'full_name' => "Aluno Fictício {$n} {$i}", 'birth_date' => '2017-03-01']);
            $entries[] = StudentEnrollment::query()->create(['id' => Str::uuid()->toString(), 'student_id' => $student->id, 'school_id' => self::ESCOLA_A1, 'school_year_id' => $year, 'school_class_id' => $class->id, 'status' => 'active', 'started_at' => '2026-02-02']);
        }

        return [$class, $entries];
    }

    private function login(string $role, array $scopes = [], bool $twoFactor = true, ?string $email = null): void
    {
        $user = $this->criarUsuario($email ?? Str::uuid()->toString().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $scopes);
        if (! $twoFactor) {
            $this->actingAs($user);

            return;
        }
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', ['code' => app(Totp::class)->at($secret, now()->getTimestamp())])->assertRedirect();
    }

    private function url(?string $class = null): string
    {
        return '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/turmas/'.($class ?? $this->ids['class']).'/conselho-de-classe';
    }

    private function payload(): array
    {
        return ['periodo' => $this->ids['period'], 'meeting_date' => '2026-04-15', 'participants' => 'Direção fictícia',
            'students' => [$this->ids['enrollment'] => ['opinion' => 'Parecer fictício reservado', 'referral' => 'team', 'observation' => 'Observação fictícia reservada']]];
    }

    public function test_conselho_dados_do_periodo_gravacao_conclusao_reabertura_e_privacidade(): void
    {
        $this->login('school_director', [self::ESCOLA_A1]);
        $this->get($this->url())->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertSee('Conselho de classe')->assertSee('Aluno Fictício 1 2')->assertSee('Componente Fictício')->assertSee('8.5')->assertSee('Alfabética');
        $this->post($this->url(), $this->payload())->assertRedirect();
        $this->get($this->url())->assertOk()->assertSee('Parecer fictício reservado')->assertSee('Abrir solicitação à equipe multiprofissional');
        $this->post($this->url().'/situacao', ['periodo' => $this->ids['period'], 'acao' => 'complete'])->assertRedirect();
        $this->post($this->url(), $this->payload())->assertStatus(409);
        $this->post($this->url().'/situacao', ['periodo' => $this->ids['period'], 'acao' => 'reopen'])->assertSessionHasErrors('motivo');
        $this->post($this->url().'/situacao', ['periodo' => $this->ids['period'], 'acao' => 'reopen', 'motivo' => 'Revisão pedagógica fictícia'])->assertRedirect();
        $this->tenant(function (): void {
            $this->assertSame('in_progress', ClassCouncil::query()->sole()->status);
            $this->assertSame('team', ClassCouncilStudent::query()->sole()->referral);
            $log = AuditLog::query()->where('action', 'pedagogical.council.student.updated')->sole();
            $this->assertArrayHasKey('changed_fields', $log->after);
            $this->assertStringNotContainsString('Parecer fictício reservado', json_encode($log->after));
            $this->assertSame(0, MultiprofessionalRequest::query()->count());
        });
        $this->login('municipal_manager');
        $this->get('/m/municipio-a/gestao-pedagogica')->assertOk()->assertDontSee('Aluno Fictício 1 1')->assertDontSee('Parecer fictício reservado');
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/gestao-pedagogica?nominal=1')->assertOk()->assertSee('Aluno Fictício 1 1')->assertDontSee('Parecer fictício reservado');
        $this->post($this->url(), $this->payload())->assertForbidden();
    }

    public function test_acesso_do_professor_vinculado_e_recusas(): void
    {
        $otherClass = $this->tenant(fn () => $this->createClass($this->ids['year'], 2)[0]->id);
        $this->login('teacher', [self::ESCOLA_A1], email: 'docente-council@example.invalid');
        $this->get($this->url())->assertOk();
        $this->get($this->url($otherClass))->assertForbidden();
        $this->post($this->url(), $this->payload())->assertForbidden();
        $this->post($this->url(), ['periodo' => $this->ids['period'], 'students' => [$this->ids['enrollment'] => ['opinion' => 'Parecer fictício']]])->assertRedirect();
        $this->post($this->url().'/situacao', ['periodo' => $this->ids['period'], 'acao' => 'complete'])->assertForbidden();

        $this->login('teacher', [self::ESCOLA_A2]);
        $this->get($this->url())->assertForbidden();
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/gestao-pedagogica?nominal=1')->assertForbidden();
        $this->login('school_director', [self::ESCOLA_A2]);
        $this->get($this->url())->assertForbidden();
        $this->login('school_director', [self::ESCOLA_A1], false);
        $this->post($this->url(), $this->payload())->assertForbidden();
        $this->login('school_secretary', [self::ESCOLA_A1]);
        $this->get($this->url())->assertForbidden();
    }

    public function test_painel_e_conselho_sem_n_mais_um(): void
    {
        $this->tenant(function (): void {
            $year = SchoolYear::query()->findOrFail($this->ids['year']);
            $class = SchoolClass::query()->findOrFail($this->ids['class']);
            $period = AcademicPeriod::query()->findOrFail($this->ids['period']);
            $panel = app(PedagogicalPanel::class);
            $roster = app(CouncilRoster::class);
            $db = DB::connection('tenant');
            $db->enableQueryLog();
            try {
                $db->flushQueryLog();
                $first = $panel->forYear($year);
                $roster->forPeriod($class, $period, null);
                $queries = count($db->getQueryLog());
                foreach (range(2, 4) as $n) {
                    $this->createClass($year->id, $n);
                }
                $student = Student::query()->create(['id' => Str::uuid()->toString(), 'full_name' => 'Aluno Fictício Extra', 'birth_date' => '2017-03-01']);
                StudentEnrollment::query()->create(['id' => Str::uuid()->toString(), 'student_id' => $student->id, 'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id, 'school_class_id' => $class->id, 'status' => 'active']);
                $db->flushQueryLog();
                $second = $panel->forYear($year);
                $roster->forPeriod($class, $period, null);
                $this->assertSame($queries, count($db->getQueryLog()));
            } finally {
                $db->disableQueryLog();
            }
            $this->assertSame(2, $first['distorted']);
            $this->assertSame(9, $second['distorted']);
            $this->assertSame(9, $second['eligible']);
        });
    }
}
