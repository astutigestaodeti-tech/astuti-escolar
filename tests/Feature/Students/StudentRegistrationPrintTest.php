<?php

namespace Tests\Feature\Students;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\Guardian;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentGuardian;
use App\Models\Tenant\StudentHealthAlert;
use App\Models\Tenant\StudentPickupAuthorization;
use App\Models\Tenant\StudentSpecialEducation;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class StudentRegistrationPrintTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private string $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = app(TenantContext::class)->runFor($this->municipioA, function (): string {
            $year = SchoolYear::query()->create(['id' => (string) Str::uuid(), 'year' => 2027, 'start_date' => '2027-02-01', 'end_date' => '2027-12-18', 'status' => 'active']);
            $student = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => 'Estudante Fictício', 'address_street' => 'Rua Sintética de Teste']);
            StudentEnrollment::query()->create(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id, 'status' => 'active']);
            $guardian = Guardian::query()->create(['id' => (string) Str::uuid(), 'full_name' => 'Responsável Fictício', 'phone' => '11987654321']);
            StudentGuardian::query()->create(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'guardian_id' => $guardian->id, 'relationship' => 'mother', 'is_primary_contact' => true]);
            StudentPickupAuthorization::query()->create(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'full_name' => 'Pessoa Vencida', 'relationship' => 'Vizinha', 'phone' => '11955554444', 'expires_at' => today()->subDay()]);
            StudentHealthAlert::query()->create(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'kind' => 'allergy', 'severity' => 'critical', 'alert_text' => 'Alerta sintético grave', 'care_instructions' => 'Cuidado sintético']);
            StudentHealthAlert::query()->create(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'kind' => 'care', 'severity' => 'attention', 'alert_text' => 'Alerta sintético não grave']);
            StudentSpecialEducation::query()->create(['student_id' => $student->id, 'conditions' => ['baixa_visao'], 'aee_status' => 'participa', 'updated_by' => $this->auditFixtureUser->id]);

            return $student->id;
        });
    }

    private function signIn(string $role = 'school_secretary', array $schools = [self::ESCOLA_A1], bool $verify = true): void
    {
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schools);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user);
        if ($verify) {
            $this->post('/seguranca/segundo-fator/desafio', ['code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp())])->assertRedirect();
        }
    }

    private function url(string $school = self::ESCOLA_A1): string
    {
        return '/m/municipio-a/escolas/'.$school.'/alunos/'.$this->student.'/imprimir';
    }

    public function test_ficha_administrativa_seleciona_grupos_e_audita_sem_conteudo(): void
    {
        $this->signIn();
        $this->get($this->url())->assertOk()
            ->assertSee('Selecione as informações')
            ->assertSee('value="foto" checked', false)
            ->assertDontSee('value="saude" checked', false)
            ->assertDontSee('value="educacao_especial" checked', false)
            ->assertDontSee('Alerta sintético grave');

        $response = $this->post($this->url(), ['grupos' => ['matricula', 'endereco', 'emergencia', 'retirada', 'foto']])->assertOk()
            ->assertSee('Estudante Fictício')->assertSee('Rua Sintética de Teste')->assertSee('Responsável Fictício')
            ->assertSee('Sem foto')->assertSee('Uso administrativo restrito')->assertSee('Imprimir / PDF')
            ->assertDontSee('Pessoa Vencida')->assertDontSee('Alerta sintético grave')->assertDontSee('Baixa visão');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $log = app(TenantContext::class)->runFor($this->municipioA, fn () => AuditLog::query()->where('action', 'student.registration_print.generated')->sole());
        $this->assertSame($this->student, $log->auditable_id);
        $this->assertSame(auth()->id(), $log->user_id);
        $this->assertEquals(['printed_groups' => 'foto,endereco,emergencia,retirada,matricula', 'print_purpose' => null], $log->after);
        $this->assertStringNotContainsString('Rua Sintética', json_encode($log->after, JSON_UNESCAPED_UNICODE));

        $this->post($this->url(), ['grupos' => ['matricula']])->assertOk()
            ->assertDontSee('Rua Sintética de Teste')->assertDontSee('Responsável Fictício')->assertDontSee('Sem foto');
    }

    public function test_dados_sensiveis_exigem_selecao_e_finalidade_e_sao_minimizados(): void
    {
        $this->signIn();
        $invalid = $this->post($this->url(), ['grupos' => ['foto', 'saude']])->assertStatus(422)
            ->assertSee('Selecione a finalidade para incluir informações sensíveis.')
            ->assertSee('Selecione as informações')
            ->assertSee('value="foto" checked', false)
            ->assertSee('value="saude" checked', false)
            ->assertDontSee('value="endereco" checked', false)
            ->assertDontSee('value="educacao_especial" checked', false)
            ->assertDontSee('Alerta sintético grave')
            ->assertSessionMissing('_old_input');
        $this->assertStringContainsString('no-store', (string) $invalid->headers->get('Cache-Control'));
        $this->postJson($this->url(), ['grupos' => ['saude']])->assertStatus(422);
        $this->assertSame(0, app(TenantContext::class)->runFor($this->municipioA, fn () => AuditLog::query()->where('action', 'student.registration_print.generated')->count()));
        $this->post($this->url(), ['grupos' => ['saude', 'educacao_especial'], 'finalidade' => 'seguranca_escolar'])
            ->assertOk()->assertSee('Alerta sintético grave')->assertSee('Cuidado sintético')->assertSee('Baixa visão')
            ->assertSee('Frequenta o AEE')->assertDontSee('Alerta sintético não grave')->assertDontSee('Rua Sintética de Teste');
        $log = app(TenantContext::class)->runFor($this->municipioA, fn () => AuditLog::query()->where('action', 'student.registration_print.generated')->sole());
        $this->assertEquals(['printed_groups' => 'saude,educacao_especial', 'print_purpose' => 'seguranca_escolar'], $log->after);
    }

    public function test_ficha_compacta_reune_grupos_sem_expor_campos_nao_selecionados(): void
    {
        $this->signIn();
        $this->post($this->url(), ['grupos' => ['responsaveis']])->assertOk()
            ->assertSee('Responsável Fictício')->assertDontSee('Telefone:')->assertDontSee('Contatos para emergência');
        $this->post($this->url(), ['grupos' => ['emergencia']])->assertOk()
            ->assertSee('Responsável Fictício')->assertSee('Telefone:')->assertDontSee('Pais e responsáveis cadastrados');
        $combined = $this->post($this->url(), ['grupos' => ['responsaveis', 'emergencia', 'matricula', 'transporte']])->assertOk()
            ->assertSee('Telefone:')->assertSee('Turma:')->assertSee('Transporte:');
        $this->assertSame(1, substr_count($combined->getContent(), 'Responsável Fictício'));
        $this->assertSame(1, substr_count($combined->getContent(), 'Ano letivo 2027'));
        $this->post($this->url(), ['grupos' => ['matricula']])->assertOk()
            ->assertSee('Turma:')->assertDontSee('Transporte:');
        $this->post($this->url(), ['grupos' => ['transporte']])->assertOk()
            ->assertSee('Transporte:')->assertDontSee('Turma:');
    }

    public function test_impressao_preserva_numero_zero_e_etapa_individual_da_matricula(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $student = Student::query()->findOrFail($this->student);
            $student->update(['address_number' => '0']);
            $enrollment = $student->enrollments()->sole();
            $class = SchoolClass::query()->create([
                'id' => (string) Str::uuid(),
                'school_id' => self::ESCOLA_A1,
                'school_year_id' => $enrollment->school_year_id,
                'name' => 'Turma Multi Fictícia',
                'stage' => 'Ensino fundamental multisseriado',
                'modality' => 'regular',
                'shift' => 'morning',
                'status' => 'active',
            ]);
            $enrollment->update(['school_class_id' => $class->id, 'inep_stage_code' => 15]);
        });
        $this->signIn();
        $this->post($this->url(), ['grupos' => ['endereco', 'matricula']])->assertOk()
            ->assertSee('Rua Sintética de Teste, 0')
            ->assertSee('Etapa: 2º ano')
            ->assertDontSee('Etapa: Ensino fundamental multisseriado');

        app(TenantContext::class)->runFor($this->municipioA, fn () => Student::query()->findOrFail($this->student)->update(['address_street' => null]));
        $this->post($this->url(), ['grupos' => ['endereco']])->assertOk()
            ->assertSee('<dd>0</dd>', false);
        $this->post($this->url(), ['grupos' => ['foto']])->assertOk()
            ->assertDontSee('Rua Sintética de Teste, 0')
            ->assertDontSee('Etapa: 2º ano');
    }

    public function test_rejeita_grupos_invalidos_e_nao_amplia_acesso(): void
    {
        $this->signIn();
        foreach ([[], ['equipe'], ['foto', 'foto'], ['saude']] as $groups) {
            $this->post($this->url(), ['grupos' => $groups])->assertStatus(422);
        }
        $this->post($this->url(), ['grupos' => ['foto'], 'finalidade' => 'texto livre'])->assertStatus(422);
        $this->get($this->url(self::ESCOLA_A2))->assertForbidden();
        $this->post(str_replace('municipio-a', 'municipio-b', $this->url()), ['grupos' => ['foto']])->assertForbidden();
        $this->assertSame(0, app(TenantContext::class)->runFor($this->municipioA, fn () => AuditLog::query()->where('action', 'student.registration_print.generated')->count()));
    }

    public function test_professor_nao_imprime_e_segundo_fator_e_obrigatorio(): void
    {
        $this->signIn('teacher');
        $this->get(str_replace('/imprimir', '', $this->url()))->assertOk()->assertDontSee('Imprimir ficha');
        $this->get($this->url())->assertForbidden();
        $this->post($this->url(), ['grupos' => ['foto']])->assertForbidden();

        $this->signIn(verify: false);
        $this->get($this->url())->assertRedirect(route('two-factor.challenge'));
        $this->post($this->url(), ['grupos' => ['foto']])->assertForbidden();
    }

    public function test_confirmacao_recente_expirada_nao_gera_documento(): void
    {
        $this->signIn();
        $this->travel(16)->minutes();
        try {
            $this->postJson($this->url(), ['grupos' => ['saude'], 'finalidade' => 'seguranca_escolar'])->assertForbidden();
            $this->post($this->url(), ['grupos' => ['saude'], 'finalidade' => 'seguranca_escolar'])
                ->assertRedirect($this->url())
                ->assertSessionHas('print_notice')
                ->assertSessionMissing('astuti.two_factor.pending_input');
            $this->get($this->url())->assertRedirect(route('two-factor.challenge'));
            $this->assertSame(0, app(TenantContext::class)->runFor($this->municipioA, fn () => AuditLog::query()->where('action', 'student.registration_print.generated')->count()));
        } finally {
            $this->travelBack();
        }
    }
}
