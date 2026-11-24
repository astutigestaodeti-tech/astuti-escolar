<?php

namespace Tests\Feature\Schools;

use App\Calendar\SchoolCalendar;
use App\Models\Tenant\AcademicPeriod;
use App\Models\Tenant\ActiveSearchCase;
use App\Models\Tenant\ActiveSearchContact;
use App\Models\Tenant\AttendanceMark;
use App\Models\Tenant\AttendanceSession;
use App\Models\Tenant\ClassTeacherAssignment;
use App\Models\Tenant\CurricularComponent;
use App\Models\Tenant\GradeEntry;
use App\Models\Tenant\MultiprofessionalRequest;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolCalendarDay;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\WaitlistRequest;
use App\Schools\TodayAtSchool;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class TodayAtSchoolTest extends TwoMunicipalityTestCase
{
    private string $yearId;

    private string $classId;

    private string $otherId;

    private string $lastDay;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2027-04-15 10:00:00'));
        config()->set('waitlist.cutoff_date', '2027-03-31');
        $this->tenant(function (): void {
            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(), 'year' => 2027, 'start_date' => '2027-02-01',
                'end_date' => '2027-12-18', 'status' => 'active',
            ]);
            $this->yearId = $year->id;
            $period = AcademicPeriod::query()->where('school_year_id', $year->id)->where('number', 1)->sole();
            $period->update(['ends_on' => '2027-04-20']);
            $class = $this->room('3º Ano A');
            $other = $this->room('4º Ano B');
            $this->room('Maternal I', 'Educação Infantil');
            $this->classId = $class->id;
            $this->otherId = $other->id;
            $entry = $this->enroll('Estudante Fictício Reservado', $class->id);
            $contacted = $this->enroll('Outro Estudante Fictício', $class->id);
            $otherEntry = $this->enroll('Nome Fictício Repetido', $other->id);
            $this->enroll('Nome Fictício Repetido', null);
            // Duas crianças alertadas, apenas uma já recebeu contato.
            $session = AttendanceSession::query()->create([
                'id' => (string) Str::uuid(), 'school_class_id' => $class->id,
                'attendance_date' => '2027-04-12', 'recorded_at' => now(), 'recorded_by' => $this->auditFixtureUser->id,
            ]);
            foreach ([$entry, $contacted] as $student) {
                AttendanceMark::query()->create([
                    'id' => (string) Str::uuid(), 'attendance_session_id' => $session->id,
                    'student_id' => $student->student_id, 'status' => 'absent',
                ]);
            }
            $case = ActiveSearchCase::query()->create([
                'id' => (string) Str::uuid(), 'enrollment_id' => $contacted->id, 'school_id' => self::ESCOLA_A1,
                'school_year_id' => $year->id, 'student_id' => $contacted->student_id, 'status' => 'open',
                'opened_at' => now(), 'updated_by' => $this->auditFixtureUser->id,
            ]);
            ActiveSearchContact::query()->create([
                'id' => (string) Str::uuid(), 'active_search_case_id' => $case->id,
                'contact_date' => today(), 'channel' => 'phone', 'outcome' => 'no_answer', 'recorded_by' => $this->auditFixtureUser->id,
            ]);
            SchoolCalendarDay::query()->create([
                'id' => (string) Str::uuid(), 'school_year_id' => $year->id, 'school_id' => self::ESCOLA_A1,
                'date' => '2027-04-14', 'kind' => 'non_school_day', 'description' => 'Reunião fictícia',
            ]);
            $this->lastDay = '2027-04-13';
            $teacher = StaffMember::query()->create([
                'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'full_name' => 'Docente fictício',
                'role' => 'teacher', 'status' => 'active', 'institutional_email' => 'hoje-docente@example.invalid',
            ]);
            ClassTeacherAssignment::query()->create([
                'id' => (string) Str::uuid(), 'school_class_id' => $class->id,
                'staff_member_id' => $teacher->id, 'subject' => 'Polivalente', 'is_main' => true, 'starts_on' => '2027-02-01',
            ]);
            $component = CurricularComponent::query()->where('active', true)->where('is_optional', false)->firstOrFail();
            GradeEntry::query()->create([
                'id' => (string) Str::uuid(), 'student_enrollment_id' => $entry->id, 'school_class_id' => $class->id,
                'academic_period_id' => $period->id, 'curricular_component_id' => $component->id,
                'grade' => 0, 'recorded_by' => $this->auditFixtureUser->id, 'updated_by' => $this->auditFixtureUser->id,
            ]);
            MultiprofessionalRequest::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $entry->student_id, 'enrollment_id' => $entry->id,
                'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id, 'area' => 'psychology',
                'reason_category' => 'family_social', 'status' => 'attending', 'requested_by' => $this->auditFixtureUser->id,
                'opened_at' => now()->subDays(3), 'assigned_to' => $this->auditFixtureUser->id, 'assigned_at' => now()->subDays(2),
                'school_guidance' => 'Conteúdo fictício reservado', 'guidance_at' => now(),
            ]);
            MultiprofessionalRequest::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $otherEntry->student_id, 'enrollment_id' => $otherEntry->id,
                'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id, 'area' => 'undetermined',
                'reason_category' => 'learning', 'status' => 'open', 'requested_by' => $this->auditFixtureUser->id,
                'opened_at' => now()->subDays(20),
            ]);
            $request = WaitlistRequest::query()->create([
                'id' => (string) Str::uuid(), 'protocol' => 'ABCDEFG234', 'child_name' => 'Criança Fictícia da Fila',
                'birth_date' => '2024-10-01', 'guardian_name' => 'Responsável Fictício',
                'guardian_phone' => '11987654321', 'postal_code' => '01001000',
                'address_street' => 'Rua Fictícia', 'address_number' => '10', 'address_district' => 'Bairro Teste',
                'address_city' => 'Cidade Inventada', 'address_state' => 'SP', 'intake_year' => 2027,
                'cutoff_date' => '2027-03-31', 'stage' => 'maternal_1', 'desired_shift' => 'manha',
                'priority_criteria' => [], 'priority_count' => 0, 'status' => 'convocado',
                'requested_at' => now(), 'created_by' => $this->auditFixtureUser->id,
            ]);
            $request->preferences()->create(['id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'preference_order' => 1]);
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

    private function room(string $name, string $stage = 'Ensino Fundamental'): SchoolClass
    {
        return SchoolClass::query()->create([
            'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'school_year_id' => $this->yearId,
            'name' => $name, 'stage' => $stage, 'modality' => 'Regular', 'shift' => 'Manhã', 'status' => 'active',
        ]);
    }

    private function enroll(string $name, ?string $class): StudentEnrollment
    {
        $student = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => $name]);

        return StudentEnrollment::query()->create([
            'id' => (string) Str::uuid(), 'student_id' => $student->id, 'school_id' => self::ESCOLA_A1,
            'school_year_id' => $this->yearId, 'school_class_id' => $class, 'status' => 'active',
        ]);
    }

    private function login(string $role, array $schools = [self::ESCOLA_A1], ?string $email = null): void
    {
        $user = $this->criarUsuario($email ?? Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schools);
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at($secret, now()->getTimestamp()),
        ])->assertRedirect();
    }

    private function url(string $school = self::ESCOLA_A1): string
    {
        return '/m/municipio-a/escolas/'.$school;
    }

    public function test_prioridades_contagens_links_e_minimizacao(): void
    {
        $this->login('school_director');
        $response = $this->get($this->url())->assertOk()->assertSee('Hoje na escola')
            ->assertDontSee('Estudante Fictício Reservado')->assertDontSee('Conteúdo fictício reservado')
            ->assertDontSee('Criança Fictícia da Fila');
        $items = $response->viewData('todayItems');
        $this->assertSame(array_keys(TodayAtSchool::LABELS), array_keys($items));
        $this->assertSame(1, $items['absence']['count']);
        $this->assertSame(2, $items['repetido']['count']);
        $this->assertSame(4, $items['responsavel']['count']);
        $this->assertSame(1, $items['turma']['count']);
        $this->assertSame(1, $items['guidance']['count']);
        $this->assertSame(1, $items['waiting']['count']);
        $this->assertSame(2, $items['attendance']['count']);
        $this->assertSame($this->lastDay, $items['attendance']['links'][0]['params']['data']);
        $required = $this->tenant(fn (): int => CurricularComponent::query()->where('active', true)->where('is_optional', false)->count());
        $this->assertSame(3 * $required - 1, $items['grades']['count']);
        $this->assertSame(1, $items['waitlist']['count']);
        $this->get('/m/municipio-a/escolas')->assertOk()->assertSee('Hoje na escola')->assertDontSee('Estudante Fictício Reservado');
        $this->get($this->url().'/alunos?equipe=guidance')->assertOk()->assertSee('Estudante Fictício Reservado')->assertDontSee('Outro Estudante Fictício');
        $this->get($this->url().'/busca-ativa?ano=2027&sem_contato=1')->assertOk()->assertSee('Exibindo alertas sem contato registrado');
    }

    public function test_professor_so_recebe_chamada_e_notas_das_atribuicoes_vigentes(): void
    {
        $this->login('teacher', email: 'hoje-docente@example.invalid');
        $response = $this->get($this->url())->assertOk();
        $items = $response->viewData('todayItems');
        $this->assertSame(['attendance', 'grades'], array_keys($items));
        $this->assertSame(1, $items['attendance']['count']);
        foreach ($items as $item) {
            foreach ($item['links'] as $link) {
                $this->assertSame($this->classId, $link['params']['turma']);
            }
        }
        $response->assertDontSee('Alertas de faltas sem contato registrado')->assertDontSee('Devolutivas novas da equipe multiprofissional');
        $this->get($this->url(self::ESCOLA_A2))->assertForbidden();
        $this->get($this->url().'/alunos?equipe=guidance')->assertForbidden();
        $this->login('teacher');
        $this->get($this->url())->assertOk()->assertSee('Tudo em dia');
    }

    public function test_secretaria_e_rede_recebem_resumo_sem_ampliar_acesso_a_equipe(): void
    {
        $this->login('school_secretary');
        $this->get($this->url())->assertOk()->assertSee('Devolutivas novas da equipe multiprofissional')->assertDontSee('Conteúdo fictício reservado');
        $this->get($this->url().'/alunos?equipe=guidance')->assertForbidden();
        $this->get('/m/municipio-a/painel-da-rede')->assertForbidden();
        $this->login('municipal_manager', []);
        $response = $this->get('/m/municipio-a/painel-da-rede')->assertOk()->assertSee('Hoje na escola - resumo da rede')
            ->assertDontSee('Estudante Fictício Reservado');
        $row = $response->viewData('rows')->first(fn (array $row): bool => $row['school']->id === self::ESCOLA_A1);
        $this->assertNull($row['today']['guidance']['count']);
        $this->assertNull($row['today']['waiting']['count']);
        $this->get($this->url(self::ESCOLA_A2))->assertOk()->assertSee('Tudo em dia');
    }

    public function test_consultas_do_servico_nao_crescem_por_aluno_ou_turma(): void
    {
        $this->tenant(function (): void {
            $year = SchoolYear::query()->findOrFail($this->yearId);
            $school = School::query()->findOrFail(self::ESCOLA_A1);
            $service = app(TodayAtSchool::class);
            $db = DB::connection('tenant');
            $db->enableQueryLog();
            try {
                $service->forSchool($school, $year, today(), waitlist: true);
                $before = count($db->getQueryLog());
                $db->disableQueryLog();
                for ($i = 1; $i <= 20; $i++) {
                    $class = $this->room('Turma Fictícia '.$i);
                    $this->enroll('Estudante Fictício '.$i, $class->id);
                }
                $db->flushQueryLog();
                $db->enableQueryLog();
                $service->forSchool($school, $year, today(), waitlist: true);
                $this->assertSame($before, count($db->getQueryLog()));
                $this->assertLessThanOrEqual(18, $before);
            } finally {
                $db->disableQueryLog();
                $db->flushQueryLog();
            }
        });
    }

    public function test_calendario_respeita_reposicao_e_interrupcao(): void
    {
        $this->tenant(function (): void {
            $year = SchoolYear::query()->findOrFail($this->yearId);
            $calendar = app(SchoolCalendar::class);
            $this->assertSame('2027-04-13', $calendar->lastSchoolDay($year, $calendar->entries($year->id, self::ESCOLA_A1), today())->toDateString());
            SchoolCalendarDay::query()->create([
                'id' => (string) Str::uuid(), 'school_year_id' => $year->id, 'school_id' => self::ESCOLA_A1,
                'date' => '2027-04-17', 'kind' => 'extra_school_day', 'description' => 'Reposição fictícia',
            ]);
            $this->assertSame('2027-04-17', $calendar->lastSchoolDay($year, $calendar->entries($year->id, self::ESCOLA_A1), Carbon::parse('2027-04-19'))->toDateString());
        });
    }
}
