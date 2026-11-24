<?php

namespace Tests\Feature\Students;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentSpecialEducation;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class SpecialEducationTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private string $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2027-04-15 15:00:00'));
        $this->student = app(TenantContext::class)->runFor($this->municipioA, function (): string {
            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(), 'year' => 2027,
                'start_date' => '2027-02-01', 'end_date' => '2027-12-15', 'status' => 'active',
            ]);
            $student = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => 'Lia Especial Fictícia', 'race_color' => 'parda', 'residence_zone' => 'urban']);
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $student->id, 'school_id' => self::ESCOLA_A1,
                'school_year_id' => $year->id, 'status' => 'active', 'transport_status' => 'not_requested',
            ]);

            return $student->id;
        });
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function entrar(string $papel, array $escolas = [self::ESCOLA_A1]): void
    {
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $papel, $escolas);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp()),
        ])->assertRedirect();
    }

    private function url(string $sufixo = ''): string
    {
        return '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$this->student.$sufixo;
    }

    public function test_secretaria_registra_categorias_do_censo_aee_e_recursos_e_a_auditoria_nao_guarda_valores(): void
    {
        $this->entrar('school_secretary');
        $this->get($this->url('/educacao-especial'))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Categorias do Censo')->assertSee('Transtorno do espectro autista (TEA)');

        $this->post($this->url('/educacao-especial'), [
            'conditions' => ['tea', 'baixa_visao'],
            'exam_resources' => ['prova_ampliada', 'tempo_adicional'],
            'aee_status' => 'participa',
            'aee_school_id' => self::ESCOLA_A2,
            'aee_started_on' => '2027-03-01',
            'support_professional' => 'sim',
        ])->assertRedirect($this->url().'#educacao-especial');

        $this->get($this->url())->assertOk()
            ->assertSee('Educação especial e AEE')
            ->assertSee('Transtorno do espectro autista (TEA)')
            ->assertSee('Frequenta o AEE')
            ->assertSee('Prova ampliada (fonte 18), Tempo adicional');

        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $record = StudentSpecialEducation::query()->findOrFail($this->student);
            $this->assertSame(['tea', 'baixa_visao'], $record->conditions);
            $this->assertTrue($record->support_professional);
            $log = AuditLog::query()->where('action', 'special_education.created')->firstOrFail();
            $json = json_encode([$log->before, $log->after]);
            $this->assertStringContainsString('changed_fields', $json);
            $this->assertStringNotContainsString('tea', str_replace('changed_fields', '', $json));
            $this->assertStringNotContainsString('prova_ampliada', $json);
        });

        // Deixar de frequentar limpa a escola e a data do AEE.
        $this->post($this->url('/educacao-especial'), [
            'conditions' => ['tea'], 'aee_status' => 'nao_participa', 'aee_school_id' => self::ESCOLA_A2,
        ])->assertRedirect();
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $record = StudentSpecialEducation::query()->findOrFail($this->student);
            $this->assertNull($record->aee_school_id);
            $this->assertNull($record->aee_started_on);
        });
    }

    public function test_transtornos_atividades_do_aee_e_recursos_seguem_o_anexo_4_do_censo(): void
    {
        $this->entrar('school_director');

        // Prova ampliada não serve para cegueira; Libras não serve para dislexia.
        $this->post($this->url('/educacao-especial'), [
            'conditions' => ['cegueira'], 'learning_disorders' => ['dislexia'],
            'exam_resources' => ['braille', 'prova_ampliada', 'libras'],
        ])->assertSessionHasErrors(['exam_resources']);
        app(TenantContext::class)->runFor($this->municipioA, fn () => $this->assertNull(StudentSpecialEducation::query()->find($this->student)));

        $this->post($this->url('/educacao-especial'), [
            'conditions' => ['cegueira'], 'learning_disorders' => ['dislexia', 'tdah'],
            'exam_resources' => ['braille', 'material_braille', 'cd_audio', 'tempo_adicional'],
            'aee_status' => 'participa', 'aee_school_id' => self::ESCOLA_A1,
            'aee_activities' => ['braille', 'orientacao_mobilidade'],
        ])->assertRedirect();

        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $record = StudentSpecialEducation::query()->findOrFail($this->student);
            $this->assertSame(['dislexia', 'tdah'], $record->learning_disorders);
            $this->assertSame(['braille', 'orientacao_mobilidade'], $record->aee_activities);
            $log = AuditLog::query()->where('action', 'special_education.created')->firstOrFail();
            $this->assertStringNotContainsString('dislexia', json_encode([$log->before, $log->after]));
        });
        $this->get($this->url())->assertOk()->assertSee('Transtornos de aprendizagem')->assertSee('Dislexia');

        // Só altas habilidades: o Censo não aceita recurso; sem AEE, as atividades são descartadas.
        $this->post($this->url('/educacao-especial'), ['conditions' => ['altas_habilidades'], 'exam_resources' => ['tempo_adicional']])
            ->assertSessionHasErrors(['exam_resources']);
        $this->post($this->url('/educacao-especial'), [
            'conditions' => ['altas_habilidades'], 'aee_status' => 'nao_participa', 'aee_activities' => ['enriquecimento'],
        ])->assertRedirect();
        app(TenantContext::class)->runFor($this->municipioA, fn () => $this->assertSame([], StudentSpecialEducation::query()->findOrFail($this->student)->aee_activities));
    }

    public function test_validacao_de_categoria_e_escola_do_aee(): void
    {
        $this->entrar('school_director');
        $this->post($this->url('/educacao-especial'), ['conditions' => ['cid_f84'], 'aee_status' => 'participa'])
            ->assertSessionHasErrors(['conditions.0', 'aee_school_id']);
    }

    public function test_professor_nao_ve_nem_edita(): void
    {
        $this->entrar('teacher');
        $this->get($this->url('/educacao-especial'))->assertForbidden();
        $this->post($this->url('/educacao-especial'), ['conditions' => ['tea']])->assertForbidden();
        $this->get($this->url())->assertOk()->assertDontSee('Educação especial e AEE');
    }

    public function test_conferencia_do_censo_aponta_aee_nao_informado_e_soma_a_merenda_do_aee(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            StudentSpecialEducation::query()->create(['student_id' => $this->student, 'conditions' => ['tea']]);
            $outro = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => 'Rui Aee Fictício', 'race_color' => 'branca', 'residence_zone' => 'urban']);
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $outro->id, 'school_id' => self::ESCOLA_A1,
                'school_year_id' => SchoolYear::query()->value('id'), 'status' => 'active', 'transport_status' => 'not_requested',
            ]);
            StudentSpecialEducation::query()->create([
                'student_id' => $outro->id, 'conditions' => ['deficiencia_fisica'], 'aee_status' => 'participa', 'aee_school_id' => self::ESCOLA_A1,
            ]);
        });

        $this->entrar('municipal_manager', []);
        $this->get('/m/municipio-a/conferencia-do-censo')->assertOk()
            ->assertSeeInOrder(['Lia Especial Fictícia', 'Educação especial sem a situação do AEE informada'], false)
            ->assertSee('2 / 1')
            ->assertSeeInOrder(['AEE no contraturno (adicional)', '1', 'adicional', 'R$ 156,00'], false);
    }
}
