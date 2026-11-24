<?php

namespace Tests\Feature\Census;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\ClassTeacherAssignment;
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

class ClassCensusTest extends TwoMunicipalityTestCase
{
    private string $classId;

    private string $infantId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2027-05-20 10:00:00'));
        $this->tenant(function (): void {
            DB::connection('tenant')->table('schools')->where('id', self::ESCOLA_A1)->update(['inep_code' => '35999001']);
            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(), 'year' => 2027,
                'start_date' => '2027-02-01', 'end_date' => '2027-12-15', 'status' => 'active',
            ]);
            $this->classId = $this->createClass($year->id, '3º Ano A', 'Ensino Fundamental', true);
            $this->infantId = $this->createClass($year->id, 'Pré II B', 'Educação Infantil', true);
        });
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function createClass(string $yearId, string $name, string $stage, bool $withPeople): string
    {
        $class = SchoolClass::query()->create([
            'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'school_year_id' => $yearId,
            'name' => $name, 'stage' => $stage, 'modality' => 'Regular', 'shift' => 'Manhã', 'status' => 'active',
        ]);
        if ($withPeople) {
            $student = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => 'Estudante Fictício '.$name, 'birth_date' => '2019-03-01']);
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $student->id, 'school_id' => self::ESCOLA_A1,
                'school_class_id' => $class->id, 'school_year_id' => $yearId, 'status' => 'active', 'started_at' => '2027-02-01',
            ]);
            $staff = StaffMember::query()->create([
                'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'full_name' => 'Docente Fictício '.$name,
                'role' => 'teacher', 'status' => 'active',
            ]);
            ClassTeacherAssignment::query()->create([
                'id' => (string) Str::uuid(), 'school_class_id' => $class->id, 'staff_member_id' => $staff->id,
                'subject' => 'Polivalente', 'is_main' => true, 'starts_on' => '2027-02-01',
            ]);
        }

        return $class->id;
    }

    private function tenant(callable $callback): mixed
    {
        return app(TenantContext::class)->runFor($this->municipioA, $callback);
    }

    private function login(string $role, array $schools = []): void
    {
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schools);
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at($secret, now()->getTimestamp()),
        ])->assertRedirect();
    }

    private function listUrl(): string
    {
        return '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/censo/turmas';
    }

    private function classUrl(?string $id = null): string
    {
        return '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/turmas/'.($id ?? $this->classId).'/censo';
    }

    private function validData(array $override = []): array
    {
        return array_replace([
            'class_type' => '6', 'mediation' => '1', 'inep_stage_code' => '16', 'special_class' => 'nao',
            'differentiated_location' => '0', 'organization' => '1', 'alternance' => 'nao', 'bilingual_deaf' => 'nao',
            'curricular_areas' => ['3', '5', '6', '10', '11', '12', '13'],
            'horario' => [
                'monday' => ['inicio' => '07:00', 'fim' => '11:30'],
                'tuesday' => ['inicio' => '07:00', 'fim' => '11:30'],
            ],
        ], $override);
    }

    public function test_secretaria_preenche_a_turma_e_ela_fica_pronta_com_codigo_estavel_e_auditoria(): void
    {
        $this->login('school_secretary', [self::ESCOLA_A1]);

        $this->get($this->listUrl())->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Turmas no Censo')->assertSee('Sugerida')->assertSee('Ensino fundamental de 9 anos - 3º Ano')
            ->assertSee('0 de 2 prontas');
        $this->get($this->classUrl())->assertOk()->assertSee('o Astuti sugeriu a etapa')
            ->assertSee('<option value="16" selected', false);

        $this->put($this->classUrl(), $this->validData())->assertRedirect($this->listUrl());

        [$census, $log] = $this->tenant(fn (): array => [
            SchoolClassCensus::query()->findOrFail($this->classId),
            AuditLog::query()->where('action', 'class_census.created')->sole(),
        ]);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{20}$/', $census->census_code);
        $this->assertSame('07:00-11:30', $census->schedule_monday);
        $this->assertNull($census->schedule_wednesday);
        $this->assertSame([3, 5, 6, 10, 11, 12, 13], $census->curricular_areas);
        $this->assertSame($this->classId, $log->auditable_id);
        $this->get($this->listUrl())->assertSee('1 de 2 prontas')->assertSee('Pronta');

        // O código nunca muda: o Inep o devolve no ano seguinte.
        $code = $census->census_code;
        $this->put($this->classUrl(), $this->validData(['inep_stage_code' => '17']))->assertRedirect();
        $this->assertSame($code, $this->tenant(fn (): string => SchoolClassCensus::query()->findOrFail($this->classId)->census_code));
        $this->assertSame(1, $this->tenant(fn (): int => AuditLog::query()->where('action', 'class_census.updated')->count()));

        $this->expectException(\LogicException::class);
        $this->tenant(function (): void {
            $census = SchoolClassCensus::query()->findOrFail($this->classId);
            $census->census_code = 'ZZZZZZZZZZZZZZZZZZZZ';
            $census->save();
        });
    }

    public function test_regras_do_inep_impedem_gravar_combinacoes_invalidas(): void
    {
        $this->login('school_director', [self::ESCOLA_A1]);

        $this->put($this->classUrl(), $this->validData(['curricular_areas' => ['1', '3']]))->assertSessionHasErrors('curricular_areas');
        $this->put($this->classUrl(), $this->validData(['mediation' => '2', 'horario' => []]))->assertSessionHasErrors('mediation');
        $this->put($this->classUrl(), $this->validData(['horario' => ['monday' => ['inicio' => '07:03', 'fim' => '11:30']]]))->assertSessionHasErrors('horario.monday.inicio');
        $this->put($this->classUrl(), $this->validData(['horario' => []]))->assertSessionHasErrors('horario');
        $this->put($this->classUrl(), $this->validData(['organization' => '2']))->assertSessionHasErrors('organization');
        $this->put($this->classUrl(), $this->validData(['alternance' => 'sim']))->assertSessionHasErrors('alternance');
        $this->put($this->classUrl(), $this->validData(['class_type' => '5']))->assertSessionHasErrors(['inep_stage_code', 'special_class']);
        $this->put($this->classUrl(), $this->validData(['class_type' => '4', 'inep_stage_code' => null, 'special_class' => null]))->assertSessionHasErrors('complementary_activities');
        $this->assertSame(0, $this->tenant(fn (): int => SchoolClassCensus::query()->count()));

        // Educação infantil: sem forma de organização e sem componentes, mesmo se enviados.
        $this->put($this->classUrl($this->infantId), $this->validData(['inep_stage_code' => '2']))->assertRedirect();
        $infant = $this->tenant(fn () => SchoolClassCensus::query()->findOrFail($this->infantId));
        $this->assertNull($infant->organization);
        $this->assertSame([], $infant->curricular_areas);

        // AEE: sem etapa nem classe especial.
        $this->put($this->classUrl(), $this->validData(['class_type' => '5', 'inep_stage_code' => null, 'special_class' => null, 'organization' => null, 'curricular_areas' => []]))->assertRedirect();
        $this->assertSame(5, $this->tenant(fn (): int => SchoolClassCensus::query()->findOrFail($this->classId)->class_type));
    }

    public function test_valores_comuns_em_lote_com_etapa_sugerida(): void
    {
        $this->login('school_secretary', [self::ESCOLA_A1]);

        $this->post($this->listUrl().'/lote', [
            'turmas' => [$this->classId, $this->infantId],
            'aplicar_etapa_sugerida' => '1', 'mediation' => '1', 'class_type' => '6', 'organization' => '1',
            'differentiated_location' => '0', 'special_class' => 'nao', 'alternance' => 'nao', 'bilingual_deaf' => 'nao',
            'semana_inicio' => '07:00', 'semana_fim' => '11:30',
        ])->assertRedirect($this->listUrl());

        [$class, $infant] = $this->tenant(fn (): array => [
            SchoolClassCensus::query()->findOrFail($this->classId),
            SchoolClassCensus::query()->findOrFail($this->infantId),
        ]);
        $this->assertSame(16, $class->inep_stage_code);
        $this->assertSame(1, $class->organization);
        $this->assertSame('07:00-11:30', $class->schedule_friday);
        $this->assertSame(2, $infant->inep_stage_code, 'Pré II sugere pré-escola');
        $this->assertNull($infant->organization, 'educação infantil não tem forma de organização');
        // A pré-escola fica pronta; o 3º ano ainda precisa dos componentes.
        $this->get($this->listUrl())->assertSee('1 de 2 prontas')->assertSee('Marque pelo menos uma área do conhecimento');

        $this->post($this->listUrl().'/lote', ['turmas' => [$this->classId], 'semana_inicio' => '07:00', 'semana_fim' => '06:00'])
            ->assertSessionHasErrors('semana_inicio');
    }

    public function test_permissao_isolamento_e_conferencia_do_censo(): void
    {
        $this->login('teacher', [self::ESCOLA_A1]);
        $this->get($this->listUrl())->assertForbidden();
        $this->put($this->classUrl(), $this->validData())->assertForbidden();

        $this->login('school_director', [self::ESCOLA_A2]);
        $this->get($this->listUrl())->assertForbidden();
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A2.'/turmas/'.$this->classId.'/censo')->assertNotFound();

        $this->login('municipal_manager');
        $this->put($this->classUrl(), $this->validData())->assertRedirect();
        $this->get('/m/municipio-a/conferencia-do-censo')->assertOk()->assertSee('Turmas prontas para o Educacenso')->assertSee('1 de 2');
    }
}
