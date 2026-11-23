<?php

namespace Tests\Feature\Students;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentHealthAlert;
use App\Models\Tenant\StudentHealthProfile;
use App\Models\User;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class StudentHealthTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private string $student;

    private string $class;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->student, $this->class] = $this->inMunicipality(function (): array {
            $year = SchoolYear::query()->create(['id' => (string) Str::uuid(), 'year' => (int) today()->format('Y'), 'start_date' => today()->startOfYear()->toDateString(), 'end_date' => today()->endOfYear()->toDateString(), 'status' => 'active']);
            $class = SchoolClass::query()->create(['id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id, 'name' => 'Turma Fictícia da Saúde', 'stage' => 'Ensino Fundamental', 'modality' => 'Regular', 'shift' => 'Manhã', 'status' => 'active']);
            $student = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => 'Nina Fictícia da Saúde']);
            StudentEnrollment::query()->create(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id, 'school_class_id' => $class->id, 'status' => 'active']);

            return [$student->id, $class->id];
        });
    }

    private function inMunicipality(callable $callback): mixed
    {
        return app(TenantContext::class)->runFor($this->municipioA, $callback);
    }

    private function user(string $role, array $schools): User
    {
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schools);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', ['code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp())])->assertRedirect();

        return $user;
    }

    private function url(string $suffix = '', string $school = self::ESCOLA_A1): string
    {
        return '/m/municipio-a/escolas/'.$school.'/alunos/'.$this->student.'/saude'.$suffix;
    }

    private function ficha(): string
    {
        return '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$this->student;
    }

    private function addAlerts(): void
    {
        $this->post($this->url('/alertas'), ['kind' => 'allergy', 'severity' => 'critical', 'alert_text' => 'Alergia grave a amendoim', 'care_instructions' => 'Não oferecer; em caso de contato, chamar o SAMU (192) e a família.'])
            ->assertRedirect($this->url())->assertSessionHas('status', 'Alerta de saúde adicionado.');
        $this->post($this->url('/alertas'), ['kind' => 'care', 'severity' => 'attention', 'alert_text' => 'Usa óculos para leitura'])->assertRedirect($this->url());
    }

    public function test_gestao_registra_alertas_e_tipo_sanguineo_com_auditoria_sem_o_valor(): void
    {
        $this->user('school_secretary', [self::ESCOLA_A1]);

        $this->get($this->url())->assertOk()->assertSee('Nenhum alerta de saúde informado pela família.')->assertSee('Não escreva diagnóstico, CID ou nome de laudo');

        $this->post($this->url('/alertas'), ['kind' => 'allergy', 'severity' => 'critical', 'alert_text' => 'Alergia a camarão'])
            ->assertSessionHasErrors(['care_instructions' => 'Para alerta grave, diga o que o professor deve fazer.']);
        $this->addAlerts();

        $this->post($this->url(), ['blood_type' => 'O+', 'informed_by' => 'Mãe, na matrícula', 'informed_on' => today()->toDateString()])
            ->assertSessionHas('status', 'Ficha de saúde atualizada.');
        $this->post($this->url(), ['blood_type' => 'Z+'])->assertSessionHasErrors(['blood_type' => 'Escolha um tipo sanguíneo da lista.']);

        $this->assertSame('O+', $this->inMunicipality(fn () => StudentHealthProfile::query()->findOrFail($this->student)->blood_type));

        $this->get($this->ficha())->assertOk()
            ->assertSeeInOrder(['Saúde e cuidados', 'Grave', 'Alergia grave a amendoim', 'O que fazer:', 'Atenção', 'Usa óculos para leitura', 'Tipo sanguíneo:', 'O+'])
            ->assertSee('Editar ficha de saúde');

        $logs = $this->inMunicipality(fn (): array => AuditLog::query()->whereIn('action', ['health_alert.created', 'health_profile.created'])->get(['action', 'after'])->map(fn ($log) => $log->after)->all());
        $this->assertCount(3, $logs);
        foreach ($logs as $after) {
            $this->assertStringNotContainsString('amendoim', json_encode($after, JSON_UNESCAPED_UNICODE));
            $this->assertStringNotContainsString('O+', json_encode($after));
        }
    }

    public function test_professor_ve_so_alerta_grave_na_ficha_e_na_chamada_e_nao_edita(): void
    {
        $this->user('school_secretary', [self::ESCOLA_A1]);
        $this->addAlerts();
        $this->post($this->url(), ['blood_type' => 'AB-']);

        $this->user('teacher', [self::ESCOLA_A1]);
        $this->get($this->ficha())->assertOk()
            ->assertSee('Alergia grave a amendoim')
            ->assertSee('chamar o SAMU (192)')
            ->assertDontSee('Usa óculos para leitura')
            ->assertDontSee('Tipo sanguíneo:')
            ->assertDontSee('AB-')
            ->assertDontSee('Editar ficha de saúde');

        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/turmas/'.$this->class.'/chamada')->assertOk()
            ->assertSeeInOrder(['Nina Fictícia da Saúde', 'Alerta de saúde', 'Alergia grave a amendoim'])
            ->assertDontSee('Usa óculos para leitura');

        $this->get($this->url())->assertForbidden();
        $this->post($this->url('/alertas'), ['kind' => 'allergy', 'severity' => 'critical', 'alert_text' => 'Tentativa', 'care_instructions' => 'Tentativa'])->assertForbidden();
        $this->post($this->url(), ['blood_type' => 'O-'])->assertForbidden();
        $this->assertSame('AB-', $this->inMunicipality(fn () => StudentHealthProfile::query()->findOrFail($this->student)->blood_type));
    }

    public function test_remover_alerta_exige_motivo_e_nao_guarda_o_motivo_na_auditoria(): void
    {
        $this->user('school_director', [self::ESCOLA_A1]);
        $this->addAlerts();
        $alert = $this->inMunicipality(fn (): string => StudentHealthAlert::query()->where('severity', 'attention')->sole()->id);

        $this->post($this->url('/alertas/'.$alert.'/remover'), ['motivo' => 'curto'])->assertSessionHasErrors('motivo');
        $this->post($this->url('/alertas/'.$alert.'/remover'), ['motivo' => 'Família informou que não usa mais óculos.'])
            ->assertSessionHas('status', 'Alerta de saúde removido.');

        $this->assertSame(1, $this->inMunicipality(fn (): int => StudentHealthAlert::query()->count()));
        $log = $this->inMunicipality(fn () => AuditLog::query()->where('action', 'health_alert.removed')->sole());
        $this->assertSame(['motivo' => '[REDACTED]'], $log->after);
        $this->assertSame('[REDACTED]', $log->before['alert_text']);
    }

    public function test_outra_escola_nao_alcanca_a_ficha_de_saude(): void
    {
        $this->user('school_secretary', [self::ESCOLA_A1]);
        $this->addAlerts();

        $this->user('school_director', [self::ESCOLA_A2]);
        $this->get($this->url())->assertForbidden();
        $this->get($this->url('', self::ESCOLA_A2))->assertForbidden();
        $this->post($this->url('/alertas', self::ESCOLA_A2), ['kind' => 'allergy', 'severity' => 'attention', 'alert_text' => 'Tentativa de outra escola'])->assertForbidden();
        $this->assertSame(2, $this->inMunicipality(fn (): int => StudentHealthAlert::query()->count()));
    }
}
