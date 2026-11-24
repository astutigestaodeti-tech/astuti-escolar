<?php

namespace Tests\Feature\Academics;

use App\Academics\Literacy;
use App\Models\Tenant\AcademicPeriod;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\ClassTeacherAssignment;
use App\Models\Tenant\ClassYearClosure;
use App\Models\Tenant\LiteracyAssessment;
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

class LiteracyTest extends TwoMunicipalityTestCase
{
    private string $yearId;

    private string $classId;

    private string $enrollmentId;

    private string $otherEnrollmentId;

    private string $secondEnrollmentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2027-10-15 10:00:00'));
        $this->tenant(function (): void {
            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(), 'year' => 2027,
                'start_date' => '2027-02-01', 'end_date' => '2027-12-18', 'status' => 'active',
            ]);
            $this->yearId = $year->id;
            $class = SchoolClass::query()->create([
                'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1,
                'school_year_id' => $year->id, 'name' => '2º Ano A', 'stage' => 'Ensino Fundamental',
                'modality' => 'Regular', 'shift' => 'Manhã', 'status' => 'active',
            ]);
            $this->classId = $class->id;
            $other = SchoolClass::query()->create([
                'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A2,
                'school_year_id' => $year->id, 'name' => '1º Ano B', 'stage' => 'Ensino Fundamental',
                'modality' => 'Regular', 'shift' => 'Manhã', 'status' => 'active',
            ]);
            $this->enrollmentId = $this->enroll($class, 'Estudante Fictício Sondagem');
            $this->secondEnrollmentId = $this->enroll($class, 'Estudante Fictício Sem Avaliação');
            $this->otherEnrollmentId = $this->enroll($other, 'Estudante Fictício Outra Escola');
            $teacher = StaffMember::query()->create([
                'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1,
                'full_name' => 'Docente Fictício', 'role' => 'teacher',
                'status' => 'active', 'institutional_email' => 'sondagem@example.invalid',
            ]);
            ClassTeacherAssignment::query()->create([
                'id' => (string) Str::uuid(), 'school_class_id' => $class->id,
                'staff_member_id' => $teacher->id, 'subject' => 'Polivalente',
                'is_main' => true, 'starts_on' => '2027-02-01',
            ]);
        });
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function enroll(SchoolClass $class, string $name): string
    {
        $student = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => $name]);

        return StudentEnrollment::query()->create([
            'id' => (string) Str::uuid(), 'student_id' => $student->id, 'school_id' => $class->school_id,
            'school_year_id' => $class->school_year_id, 'school_class_id' => $class->id, 'status' => 'active',
        ])->id;
    }

    private function tenant(callable $callback): mixed
    {
        return app(TenantContext::class)->runFor($this->municipioA, $callback);
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

    private function url(): string
    {
        return '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/turmas/'.$this->classId.'/alfabetizacao';
    }

    private function payload(): array
    {
        return ['bimestre' => 4, 'matricula' => $this->enrollmentId, 'escrita' => 'syllabic_sound', 'leitura' => 'words'];
    }

    public function test_sondagem_exige_vinculo_da_turma_e_categorias_validas(): void
    {
        $this->login('teacher');
        $this->get($this->url())->assertForbidden();
        $this->post($this->url(), $this->payload())->assertForbidden();
        $this->login('teacher', [self::ESCOLA_A1], 'sondagem@example.invalid');
        $this->get($this->url())->assertOk()->assertDontSee('Estudante Fictício Outra Escola');
        $this->post($this->url(), [...$this->payload(), 'matricula' => $this->otherEnrollmentId])->assertNotFound();
        $this->post($this->url(), [...$this->payload(), 'escrita' => 'invalido'])->assertSessionHasErrors('escrita');
        $this->post($this->url(), $this->payload())->assertRedirect();
        $this->post($this->url(), [...$this->payload(), 'bimestre' => 1, 'escrita' => 'pre_syllabic', 'leitura' => 'none'])->assertRedirect();
        $this->tenant(function (): void {
            $this->assertSame(2, LiteracyAssessment::query()->count());
            $this->assertSame(2, AuditLog::query()->where('action', 'literacy.assessment.created')->count());
            $service = app(Literacy::class);
            $classes = $service->classes(SchoolYear::query()->findOrFail($this->yearId), self::ESCOLA_A1);
            $entries = $service->enrollments($classes);
            $rows = $service->distribution($entries, $service->assessments($entries));
            $this->assertSame(2, $rows[4]['total']);
            $this->assertSame(1, $rows[4]['assessed']);
            $this->assertSame(1, $rows[4]['missing']);
            $this->assertSame(1, $rows[4]['writing']['syllabic_sound']);
            $this->assertNull($rows[3]['alphabetic_pct']);
        });
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alfabetizacao')->assertForbidden();
    }

    public function test_painel_nominal_escolar_e_rede_agregada(): void
    {
        $this->login('school_secretary');
        $this->post($this->url(), $this->payload())->assertRedirect();
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alfabetizacao')->assertOk()->assertSee('Estudante Fictício Sondagem')->assertDontSee('Estudante Fictício Sem Avaliação');
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A2.'/alfabetizacao')->assertForbidden();
        $this->get('/m/municipio-a/alfabetizacao')->assertForbidden();
        $this->login('municipal_manager', []);
        $this->get('/m/municipio-a/alfabetizacao')->assertOk()->assertDontSee('Estudante Fictício Sondagem');
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alfabetizacao')->assertOk()->assertDontSee('Estudante Fictício Sondagem');
    }

    public function test_bimestre_e_turma_fechados_protegem_lancamento(): void
    {
        $this->tenant(function (): void {
            $period = AcademicPeriod::query()->where('school_year_id', $this->yearId)->where('number', 4)->sole();
            $period->forceFill(['closed_at' => now(), 'closed_by' => $this->auditFixtureUser->id, 'close_reason' => 'Encerramento fictício do bimestre'])->save();
        });
        $this->login('teacher', [self::ESCOLA_A1], 'sondagem@example.invalid');
        $this->post($this->url(), $this->payload())->assertSessionHasErrors('motivo');
        $this->login('school_director');
        $this->post($this->url(), [...$this->payload(), 'motivo' => 'Correção pedagógica fictícia'])->assertRedirect();
        $this->tenant(function (): void {
            $closure = ClassYearClosure::query()->findOrFail($this->classId);
            $closure->forceFill(['closed_at' => now(), 'closed_by' => $this->auditFixtureUser->id, 'close_reason' => 'Fechamento fictício'])->save();
        });
        $this->post($this->url(), [...$this->payload(), 'escrita' => 'alphabetic', 'motivo' => 'Correção pedagógica fictícia'])->assertSessionHasErrors('bimestre');
        $this->assertSame(1, $this->tenant(fn (): int => LiteracyAssessment::query()->count()));
    }

    public function test_turma_inteira_e_salva_de_uma_vez_e_linhas_vazias_ficam_como_estao(): void
    {
        $this->login('teacher', [self::ESCOLA_A1], 'sondagem@example.invalid');
        $this->get($this->url())->assertOk()->assertSee('Salvar sondagem da turma')->assertSee('name="sondagem['.$this->enrollmentId.'][escrita]"', false);

        // Linha com só um dos dois campos é recusada; aluno de outra escola não entra.
        $this->post($this->url(), ['bimestre' => 4, 'sondagem' => [$this->enrollmentId => ['escrita' => 'alphabetic', 'leitura' => '']]])->assertSessionHasErrors('sondagem');
        $this->post($this->url(), ['bimestre' => 4, 'sondagem' => [$this->otherEnrollmentId => ['escrita' => 'alphabetic', 'leitura' => 'words']]])->assertNotFound();

        $this->post($this->url(), ['bimestre' => 4, 'sondagem' => [
            $this->enrollmentId => ['escrita' => 'alphabetic', 'leitura' => 'fluent_texts'],
            $this->secondEnrollmentId => ['escrita' => 'syllabic_sound', 'leitura' => 'words'],
        ]])->assertRedirect()->assertSessionHas('status', 'Sondagem registrada para 2 alunos.');

        // Reenviar com um aluno em branco não apaga nem altera o que já foi lançado.
        $this->post($this->url(), ['bimestre' => 4, 'sondagem' => [
            $this->enrollmentId => ['escrita' => '', 'leitura' => ''],
            $this->secondEnrollmentId => ['escrita' => 'syllabic_sound', 'leitura' => 'words'],
        ]])->assertRedirect()->assertSessionHas('status', 'Nada mudou na sondagem.');

        $this->tenant(function (): void {
            $this->assertSame(2, LiteracyAssessment::query()->where('term', 4)->count());
            $this->assertSame('alphabetic', LiteracyAssessment::query()->where('student_enrollment_id', $this->enrollmentId)->value('writing'));
            $this->assertSame(2, AuditLog::query()->where('action', 'literacy.assessment.created')->count());
        });
    }
}
