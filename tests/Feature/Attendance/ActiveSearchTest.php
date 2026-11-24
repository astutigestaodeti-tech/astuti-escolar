<?php

namespace Tests\Feature\Attendance;

use App\Attendance\ActiveSearchAlerts;
use App\Models\Tenant\ActiveSearchCase;
use App\Models\Tenant\ActiveSearchContact;
use App\Models\Tenant\ActiveSearchParameter;
use App\Models\Tenant\AttendanceMark;
use App\Models\Tenant\AttendanceSession;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\ClassTeacherAssignment;
use App\Models\Tenant\IssuedDocument;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class ActiveSearchTest extends TwoMunicipalityTestCase
{
    private string $yearId;

    private string $enrollmentId;

    private string $classId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2027-10-25 14:00:00'));
        $this->tenant(function (): void {
            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(), 'year' => 2027,
                'start_date' => '2027-02-01', 'end_date' => '2027-12-15', 'status' => 'active',
            ]);
            $this->yearId = $year->id;
            $class = SchoolClass::query()->create([
                'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1,
                'school_year_id' => $year->id, 'name' => '3º Ano A',
                'stage' => 'Ensino Fundamental', 'modality' => 'Regular',
                'shift' => 'Manhã', 'status' => 'active',
            ]);
            $this->classId = $class->id;
            $student = Student::query()->create([
                'id' => (string) Str::uuid(), 'full_name' => 'Estudante Fictício Busca Ativa',
                'birth_date' => '2018-04-01',
            ]);
            $this->enrollmentId = StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $student->id,
                'school_id' => self::ESCOLA_A1, 'school_class_id' => $class->id,
                'school_year_id' => $year->id, 'status' => 'active',
                'started_at' => '2027-10-18',
            ])->id;
            foreach (range(18, 22) as $date) {
                $session = AttendanceSession::query()->create([
                    'id' => (string) Str::uuid(), 'school_class_id' => $class->id,
                    'attendance_date' => '2027-10-'.$date, 'recorded_at' => now(),
                    'recorded_by' => $this->auditFixtureUser->id,
                ]);
                AttendanceMark::query()->create([
                    'id' => (string) Str::uuid(), 'attendance_session_id' => $session->id,
                    'student_id' => $student->id, 'status' => 'absent',
                ]);
            }
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

    private function login(string $role, array $schools = [], ?string $email = null): void
    {
        $user = $this->criarUsuario($email ?? (string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schools);
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at($secret, now()->getTimestamp()),
        ])->assertRedirect();
    }

    private function schoolUrl(): string
    {
        return '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/busca-ativa';
    }

    private function caseUrl(): string
    {
        return $this->schoolUrl().'/'.$this->enrollmentId;
    }

    public function test_alerta_de_cinco_dias_letivos_e_parametros_da_gestao(): void
    {
        $this->tenant(function (): void {
            $service = app(ActiveSearchAlerts::class);
            $school = School::query()->findOrFail(self::ESCOLA_A1);
            $year = SchoolYear::query()->findOrFail($this->yearId);
            $summary = $service->summaries($school, $year, $service->enrollments($school, $year), today())[$this->enrollmentId];
            $this->assertSame(5, $summary['streak']);
            $this->assertEquals(0, $summary['monthly']);
            $this->assertTrue($summary['alert']);
            $this->assertSame(['days' => 5, 'percentage' => 75], $service->parameters($year));
        });
        $this->login('school_secretary', [self::ESCOLA_A1]);
        $this->get($this->schoolUrl())->assertOk()->assertSee('Estudante Fictício Busca Ativa');
        $data = ['ano_id' => $this->yearId, 'dias' => 7, 'frequencia' => 80];
        $this->post('/m/municipio-a/busca-ativa/parametros', $data)->assertForbidden();

        $this->login('municipal_manager');
        $this->post('/m/municipio-a/busca-ativa/parametros', $data)->assertRedirect();
        $this->assertSame(7, $this->tenant(fn (): int => ActiveSearchParameter::query()->sole()->consecutive_days));
        $this->get('/m/municipio-a/busca-ativa')->assertOk()->assertDontSee('Estudante Fictício Busca Ativa');
    }

    public function test_caso_contato_documento_verificavel_e_revogacao(): void
    {
        $this->login('school_director', [self::ESCOLA_A1]);
        $url = $this->caseUrl();
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->post($url, ['situacao' => 'open', 'motivo' => 'health'])->assertRedirect();
        $this->post($url.'/encaminhamento', ['confirmar' => 1])->assertSessionHasErrors('confirmar');
        $this->post($url.'/contatos', [
            'data' => '2027-10-25', 'meio' => 'phone', 'resultado' => 'contacted',
        ])->assertRedirect();
        $this->post($url.'/encaminhamento', ['confirmar' => 1])->assertRedirect();
        [$id, $code] = $this->tenant(function (): array {
            $case = ActiveSearchCase::query()->sole();
            $this->assertSame('referred', $case->status);
            $this->assertSame(1, ActiveSearchContact::query()->count());
            $log = AuditLog::query()->where('action', 'active_search.case.saved')->sole();
            $this->assertArrayNotHasKey('reason_category', $log->after);
            $document = IssuedDocument::query()->where('kind', 'active_search_referral')->sole();
            $this->assertSame(5, $document->referral_snapshot['summary']['absences']);

            return [$document->id, $document->verification_code];
        });
        $this->get($url.'/documentos/'.$id)->assertOk()->assertSee('Estudante Fictício Busca Ativa');
        config()->set('tenancy.deployed_db_key', 'municipio_a');
        $this->get('/verificar/'.$code)->assertOk()->assertSee('Documento válido')
            ->assertDontSee('Estudante')->assertDontSee('Telefone')->assertDontSee('Busca Ativa')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->post($url.'/documentos/'.$id.'/revogar', ['confirmar' => 1])->assertRedirect();
        $this->get('/verificar/'.$code)->assertOk()->assertSee('Documento revogado');
        $this->post($url, ['situacao' => 'returned', 'motivo' => 'transport'])->assertRedirect();
        $this->assertNotNull($this->tenant(fn () => ActiveSearchCase::query()->sole()->returned_at));
    }

    public function test_professor_vinculado_so_ve_alerta_da_turma(): void
    {
        $email = 'docente-busca@example.invalid';
        $this->tenant(function () use ($email): void {
            $staff = StaffMember::query()->create([
                'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1,
                'full_name' => 'Docente Fictício', 'role' => 'teacher',
                'institutional_email' => $email, 'status' => 'active',
            ]);
            ClassTeacherAssignment::query()->create([
                'id' => (string) Str::uuid(), 'school_class_id' => $this->classId,
                'staff_member_id' => $staff->id, 'subject' => 'Polivalente', 'is_main' => true,
                'starts_on' => '2027-02-01',
            ]);
        });
        $classUrl = '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/turmas/'.$this->classId.'/busca-ativa';
        $this->login('teacher', [self::ESCOLA_A1]);
        $this->get($classUrl)->assertForbidden();

        $this->login('teacher', [self::ESCOLA_A1], $email);
        $this->get($classUrl)->assertOk()->assertSee('Estudante Fictício Busca Ativa')
            ->assertDontSee('Registrar contato')->assertDontSee('Motivo informado');
        $this->get($this->caseUrl())->assertForbidden();
        $this->get($this->schoolUrl())->assertForbidden();
        $this->post($this->caseUrl(), ['situacao' => 'open'])->assertForbidden();
    }

    public function test_isolamento_de_escola_e_segundo_fator_recente(): void
    {
        $this->login('school_director', [self::ESCOLA_A2]);
        $this->get($this->caseUrl())->assertForbidden();
        $this->post($this->caseUrl(), ['situacao' => 'open'])->assertForbidden();

        $this->login('school_director', [self::ESCOLA_A1]);
        $this->post($this->caseUrl(), ['situacao' => 'open', 'motivo' => 'texto livre'])->assertSessionHasErrors('motivo');
        $this->travelTo(now()->addMinutes(20));
        $this->post($this->caseUrl(), ['situacao' => 'open'])->assertRedirect(route('two-factor.challenge'));
        $this->assertSame(0, $this->tenant(fn (): int => ActiveSearchCase::query()->count()));
    }
}
