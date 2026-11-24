<?php

namespace Tests\Feature\Multiprofessional;

use App\Models\Platform\Membership;
use App\Models\Tenant\ActiveSearchCase;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\ClassTeacherAssignment;
use App\Models\Tenant\MultiprofessionalAttendance;
use App\Models\Tenant\MultiprofessionalReferral;
use App\Models\Tenant\MultiprofessionalRequest;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\User;
use App\Multiprofessional\TeamIndicators;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class TeamWorkflowTest extends TwoMunicipalityTestCase
{
    private const QUEUE = '/m/municipio-a/equipe-multiprofissional';

    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private string $yearId;

    private string $classId;

    private string $otherClassId;

    private string $enrollmentId;

    private string $studentId;

    private string $otherEnrollmentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2027-04-15 10:00:00'));
        $this->tenant(function (): void {
            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(), 'year' => 2027,
                'start_date' => '2027-02-01', 'end_date' => '2027-12-18', 'status' => 'active',
            ]);
            $this->yearId = $year->id;
            $class = $this->makeClass(self::ESCOLA_A1, '2º Ano A');
            $other = $this->makeClass(self::ESCOLA_A2, '2º Ano B');
            $this->classId = $class->id;
            $this->otherClassId = $other->id;
            $entry = $this->enroll($class, 'Criança Fictícia Equipe');
            $this->enrollmentId = $entry->id;
            $this->studentId = $entry->student_id;
            $this->otherEnrollmentId = $this->enroll($other, 'Criança Fictícia Outra Escola')->id;
            $teacher = StaffMember::query()->create([
                'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1,
                'full_name' => 'Docente fictício', 'role' => 'teacher',
                'status' => 'active', 'institutional_email' => 'docente-equipe@example.invalid',
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

    private function tenant(callable $callback): mixed
    {
        return app(TenantContext::class)->runFor($this->municipioA, $callback);
    }

    private function makeClass(string $school, string $name): SchoolClass
    {
        return SchoolClass::query()->create([
            'id' => (string) Str::uuid(), 'school_id' => $school, 'school_year_id' => $this->yearId,
            'name' => $name, 'stage' => 'Ensino Fundamental', 'modality' => 'Regular',
            'shift' => 'Manhã', 'status' => 'active',
        ]);
    }

    private function enroll(SchoolClass $class, string $name): StudentEnrollment
    {
        $student = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => $name]);

        return StudentEnrollment::query()->create([
            'id' => (string) Str::uuid(), 'student_id' => $student->id, 'school_id' => $class->school_id,
            'school_year_id' => $class->school_year_id, 'school_class_id' => $class->id, 'status' => 'active',
        ]);
    }

    private function login(string $role, array $schools = [], ?string $email = null): User
    {
        $user = $this->criarUsuario($email ?? Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schools);
        $this->confirm($user);

        return $user;
    }

    private function confirm(User $user): void
    {
        $this->travel(31)->seconds();
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp()),
        ])->assertRedirect();
    }

    private function studentUrl(): string
    {
        return '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$this->studentId;
    }

    private function openUrl(?string $school = null, ?string $entry = null): string
    {
        return '/m/municipio-a/escolas/'.($school ?? self::ESCOLA_A1).'/equipe-multiprofissional/'.($entry ?? $this->enrollmentId);
    }

    private function openRequest(string $area = 'psychology'): string
    {
        $this->post($this->openUrl(), ['area' => $area, 'motivo' => 'learning'])->assertRedirect();

        return $this->tenant(fn (): string => MultiprofessionalRequest::query()->where('area', $area)->sole()->id);
    }

    public function test_pedido_restrito_a_direcao_ou_professor_da_turma_com_segundo_fator(): void
    {
        $payload = ['area' => 'psychology', 'motivo' => 'learning'];
        $director = $this->criarUsuario('direcao@example.invalid');
        $this->vincular($director, $this->municipioA, 'school_director', [self::ESCOLA_A1]);
        $this->actingAs($director)->post($this->openUrl(), $payload)->assertForbidden();

        foreach (['school_secretary', 'teacher'] as $role) {
            $this->login($role, [self::ESCOLA_A1]);
            $this->post($this->openUrl(), $payload)->assertForbidden();
            $this->get($this->studentUrl())->assertOk()->assertDontSee('Solicitar atendimento');
        }
        $this->login('municipal_manager');
        $this->post($this->openUrl(), $payload)->assertForbidden();
        $this->get($this->studentUrl())->assertOk()->assertDontSee('Solicitar atendimento');

        $this->login('teacher', [self::ESCOLA_A1], 'docente-equipe@example.invalid');
        $this->get($this->studentUrl())->assertOk()->assertSee('Solicitar atendimento');
        $this->post($this->openUrl(self::ESCOLA_A2, $this->otherEnrollmentId), $payload)->assertForbidden();
        $this->post($this->openUrl(), ['area' => 'psychology', 'motivo' => 'texto livre'])->assertSessionHasErrors('motivo');
        $this->openRequest();
        $this->confirm($director);
        $this->post($this->openUrl(), $payload)->assertRedirect();
        $this->assertSame(1, $this->tenant(fn (): int => MultiprofessionalRequest::query()->count()));
    }

    public function test_fluxo_respeita_area_autoria_e_retorno_sem_conteudo_na_auditoria(): void
    {
        $director = $this->login('school_director', [self::ESCOLA_A1]);
        $id = $this->openRequest();
        $url = self::QUEUE.'/'.$id;

        $this->login('assistente_social');
        $this->get(self::QUEUE)->assertOk()->assertDontSee('Criança Fictícia Equipe');
        $this->get($url)->assertForbidden();

        $professional = $this->login('psicologo');
        $this->get('/')->assertRedirect(self::QUEUE);
        $this->get('/m/municipio-a/escolas')->assertForbidden();
        $this->get($this->studentUrl())->assertForbidden();
        $this->get(self::QUEUE)->assertOk()->assertSee('Criança Fictícia Equipe');
        $this->get($url)->assertOk()->assertSee('Assumir pedido');
        $this->post($url.'/devolutiva', ['devolutiva' => 'Não autorizado'])->assertForbidden();
        $this->post($url.'/assumir', ['area' => 'psychology'])->assertRedirect();

        $this->login('psicologo');
        $this->get($url)->assertForbidden();
        $this->post($url.'/assumir', ['area' => 'psychology'])->assertForbidden();
        $this->confirm($professional);

        $this->post($url.'/agendar', ['agenda' => '2027-04-16T10:00'])->assertRedirect();
        $this->get(self::QUEUE.'?agenda=1')->assertOk()->assertSee('16/04/2027 10:00');
        $this->post($url.'/atendimento', ['data' => '2027-04-15'])->assertRedirect();
        $this->post($url.'/atendimento', ['data' => '2027-04-15'])->assertSessionHasErrors('data');
        $this->post($url.'/devolutiva', ['devolutiva' => str_repeat('x', 501)])->assertSessionHasErrors('devolutiva');
        $guidance = 'Organizar a rotina com instruções curtas e pausas.';
        $this->post($url.'/devolutiva', ['devolutiva' => $guidance])->assertRedirect();
        $this->post($url.'/encaminhar', ['destino' => 'texto livre', 'data' => '2027-04-15'])->assertSessionHasErrors('destino');
        $this->post($url.'/encaminhar', ['destino' => 'ubs', 'data' => '2027-04-15'])->assertRedirect();
        $referral = $this->tenant(fn (): string => MultiprofessionalReferral::query()->sole()->id);
        $document = $url.'/encaminhamentos/'.$referral;
        $this->get($document)->assertOk()->assertSee('Encaminhamento à rede de proteção')
            ->assertSee('doc-timbre', false)->assertDontSee($guidance)->assertDontSee('Aprendizagem');
        $this->post($url.'/encerrar', ['motivo' => 'completed'])->assertRedirect();
        $this->post($url.'/atendimento', ['data' => '2027-04-15'])->assertStatus(409);
        $this->post($document.'/retorno', ['data' => '2027-04-15', 'retorno' => 'received'])->assertRedirect();

        $this->confirm($director);
        $this->get($this->studentUrl())->assertOk()->assertSee($guidance)->assertDontSee('Motivo (categoria)');
        $this->get($url)->assertForbidden();
        $this->get($document)->assertForbidden();
        $this->login('teacher', [self::ESCOLA_A1], 'docente-equipe@example.invalid');
        $this->get($this->studentUrl())->assertOk()->assertSee($guidance)->assertDontSee('Motivo (categoria)');
        foreach (['school_secretary', 'municipal_manager'] as $role) {
            $this->login($role, $role === 'school_secretary' ? [self::ESCOLA_A1] : []);
            $this->get($this->studentUrl())->assertOk()->assertDontSee($guidance);
            $this->get($url)->assertForbidden();
        }

        $this->tenant(function () use ($guidance): void {
            $entry = MultiprofessionalRequest::query()->sole();
            $this->assertSame('closed', $entry->status);
            $this->assertSame(1, MultiprofessionalAttendance::query()->count());
            $this->assertSame('received', MultiprofessionalReferral::query()->sole()->return_category);
            $logs = AuditLog::query()->where('action', 'like', 'multiprofessional.%')->get();
            $this->assertGreaterThan(6, $logs->count());
            $this->assertStringNotContainsString($guidance, $logs->toJson());
            $this->assertStringNotContainsString('learning', $logs->toJson());
            $this->assertTrue($logs->every(fn (AuditLog $log): bool => $log->before === null && $log->after === null));
        });
    }

    public function test_busca_ativa_cria_pedido_idempotente_sem_copiar_motivo(): void
    {
        $caseId = $this->tenant(function (): string {
            $case = ActiveSearchCase::query()->create([
                'id' => (string) Str::uuid(), 'enrollment_id' => $this->enrollmentId,
                'school_id' => self::ESCOLA_A1, 'school_year_id' => $this->yearId,
                'student_id' => $this->studentId, 'status' => 'open', 'reason_category' => 'health',
                'opened_at' => now(), 'updated_by' => $this->auditFixtureUser->id,
            ]);

            return $case->id;
        });
        $this->login('school_director', [self::ESCOLA_A1]);
        $url = '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/busca-ativa/'.$this->enrollmentId.'/equipe-multiprofissional';
        $this->post($url)->assertRedirect();
        $this->post($url)->assertRedirect();
        $this->tenant(function () use ($caseId): void {
            $row = MultiprofessionalRequest::query()->sole();
            $this->assertSame($caseId, $row->active_search_case_id);
            $this->assertSame('social_work', $row->area);
            $this->assertSame('attendance', $row->reason_category);
        });
    }

    public function test_indicadores_protegem_grupo_pequeno_e_complemento_sem_dados_nominais(): void
    {
        $this->tenant(function (): void {
            $class = SchoolClass::query()->findOrFail($this->classId);
            $other = SchoolClass::query()->findOrFail($this->otherClassId);
            $actor = $this->auditFixtureUser->id;
            foreach (range(1, 7) as $number) {
                $entry = $this->enroll($number === 7 ? $other : $class, 'Criança Fictícia Indicador '.$number);
                $row = MultiprofessionalRequest::query()->create([
                    'id' => (string) Str::uuid(), 'student_id' => $entry->student_id, 'enrollment_id' => $entry->id,
                    'school_id' => $entry->school_id, 'school_year_id' => $this->yearId,
                    'area' => $number === 7 ? 'social_work' : 'psychology', 'reason_category' => 'family_social',
                    'status' => 'attending', 'requested_by' => $actor, 'opened_at' => '2027-04-01 08:00:00',
                    'assigned_to' => $actor, 'assigned_at' => '2027-04-01 09:00:00',
                    'school_guidance' => 'Orientação fictícia protegida', 'guidance_at' => '2027-04-05 08:00:00',
                ]);
                $row->attendances()->create([
                    'id' => (string) Str::uuid(), 'attended_on' => '2027-04-05', 'recorded_by' => $actor,
                ]);
                $row->referrals()->create([
                    'id' => (string) Str::uuid(), 'destination' => $number === 7 ? 'cras' : 'ubs',
                    'referred_on' => '2027-04-06', 'recorded_by' => $actor,
                ]);
            }
            $data = app(TeamIndicators::class)->forMonth(SchoolYear::query()->findOrFail($this->yearId), Carbon::parse('2027-04-01'));
            $this->assertSame(7, $data['opened']);
            $this->assertSame(7, $data['attended']);
            $this->assertSame(4.0, $data['wait_days']);
            $this->assertNull(collect($data['schools'])->firstWhere('label', 'Unidade A')['count']);
            $this->assertNull(collect($data['schools'])->firstWhere('label', 'Outra Unidade A')['count']);
            $this->assertNull(collect($data['areas'])->firstWhere('label', 'Psicologia')['count']);
            $this->assertNull(collect($data['areas'])->firstWhere('label', 'Serviço Social')['count']);
            $this->assertNull(collect($data['destinations'])->firstWhere('label', 'UBS')['count']);
            $this->assertNull(collect($data['destinations'])->firstWhere('label', 'CRAS')['count']);
            $this->assertStringNotContainsString('student_id', json_encode($data, JSON_THROW_ON_ERROR));
        });

        $this->login('municipal_manager');
        $this->get(self::QUEUE.'/indicadores?mes=2027-04')->assertOk()->assertSee('Protegido')
            ->assertDontSee('Criança Fictícia Indicador')->assertDontSee('Orientação fictícia protegida');
        $this->get('/m/municipio-a/relatorio-mensal-secretaria?mes=2027-04')->assertOk()
            ->assertSee('9. Equipe multiprofissional')->assertDontSee('Criança Fictícia Indicador')
            ->assertDontSee('Orientação fictícia protegida');
        $this->get(self::QUEUE)->assertForbidden();
    }

    public function test_muitos_atendimentos_de_um_unico_aluno_continuam_protegidos(): void
    {
        $this->tenant(function (): void {
            $actor = $this->auditFixtureUser->id;
            $entry = MultiprofessionalRequest::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $this->studentId, 'enrollment_id' => $this->enrollmentId,
                'school_id' => self::ESCOLA_A1, 'school_year_id' => $this->yearId,
                'area' => 'psychology', 'reason_category' => 'learning', 'status' => 'attending',
                'requested_by' => $actor, 'opened_at' => '2027-04-01 08:00:00',
                'assigned_to' => $actor, 'assigned_at' => '2027-04-01 09:00:00',
            ]);
            foreach (range(2, 7) as $day) {
                $entry->attendances()->create([
                    'id' => (string) Str::uuid(), 'attended_on' => '2027-04-0'.$day, 'recorded_by' => $actor,
                ]);
            }
            $data = app(TeamIndicators::class)->forMonth(SchoolYear::query()->findOrFail($this->yearId), Carbon::parse('2027-04-01'));
            $this->assertNull($data['opened']);
            $this->assertNull($data['attended']);
            $this->assertNull($data['wait_days']);
        });
    }

    public function test_conta_da_equipe_exige_ativacao_do_segundo_fator_e_nao_herda_gestao(): void
    {
        $user = $this->criarUsuario('equipe@example.invalid');
        $this->vincular($user, $this->municipioA, 'pedagogo_equipe');
        $this->actingAs($user)->get(self::QUEUE)->assertRedirect('/seguranca/segundo-fator/desafio');
        $this->confirm($user);
        $this->get(self::QUEUE)->assertOk();
        $this->get('/m/municipio-a/painel-da-rede')->assertForbidden();
        $this->get(self::QUEUE.'/indicadores')->assertForbidden();

        $this->vincular($user, $this->municipioA, 'municipal_manager');
        $this->get(self::QUEUE)->assertForbidden();
        $this->assertSame(2, Membership::query()->where('user_id', $user->id)->count());
    }

    public function test_comando_vincula_conta_individual_sem_conceder_gestao(): void
    {
        $user = $this->criarUsuario('psicologia@example.invalid');
        $this->artisan('astuti:equipe-usuario', ['municipio' => 'municipio-a', 'email' => $user->email, 'perfil' => 'psicologo'])
            ->assertSuccessful();
        $this->assertSame('psicologo', Membership::query()->where('user_id', $user->id)->sole()->role);
        $admin = $this->criarUsuario('gestao@example.invalid');
        $this->vincular($admin, $this->municipioA, 'municipal_manager');
        $this->artisan('astuti:equipe-usuario', ['municipio' => 'municipio-a', 'email' => $admin->email, 'perfil' => 'assistente_social'])
            ->assertFailed();
        $this->assertSame(1, Membership::query()->where('user_id', $admin->id)->count());
    }
}
