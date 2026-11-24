<?php

namespace Tests\Feature\Census;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\ClassTeacherAssignment;
use App\Models\Tenant\ClassTeacherAssignmentCensus;
use App\Models\Tenant\SchoolCensus;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolClassCensus;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\StaffManagerCensus;
use App\Models\Tenant\StaffMember;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class StaffLinkCensusTest extends TwoMunicipalityTestCase
{
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-05-20 10:00:00'));
        $this->ids = $this->tenant(function (): array {
            $year = SchoolYear::query()->create(['id' => (string) Str::uuid(), 'year' => 2026, 'start_date' => '2026-02-01', 'end_date' => '2026-12-18', 'status' => 'active']);
            $class = SchoolClass::query()->create(['id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id, 'name' => 'Turma Fictícia', 'stage' => 'Ensino Fundamental', 'modality' => 'Regular', 'shift' => 'Manhã', 'status' => 'active', 'capacity' => 30]);
            $teacher = StaffMember::query()->create(['id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'full_name' => 'Docente Fictício', 'role' => 'teacher', 'status' => 'active']);
            $director = StaffMember::query()->create(['id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'full_name' => 'Diretora Fictícia', 'role' => 'director', 'status' => 'active']);
            $assignment = ClassTeacherAssignment::query()->create(['id' => (string) Str::uuid(), 'school_class_id' => $class->id, 'staff_member_id' => $teacher->id, 'subject' => 'Matemática', 'is_main' => true, 'starts_on' => '2026-02-01']);
            SchoolCensus::query()->create(['school_id' => self::ESCOLA_A1, 'operating_status' => 1, 'administrative_dependency' => 3]);
            SchoolClassCensus::query()->create(['school_class_id' => $class->id, 'inep_stage_code' => 16, 'mediation' => 1, 'class_type' => 6, 'curricular_areas' => [3], 'complementary_activities' => []]);

            return ['class' => $class->id, 'teacher' => $teacher->id, 'director' => $director->id, 'assignment' => $assignment->id];
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

    private function login(string $role, array $schools = [], bool $twoFactor = true): void
    {
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schools);
        if (! $twoFactor) {
            $this->actingAs($user);

            return;
        }
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', ['code' => app(Totp::class)->at($secret, now()->getTimestamp())])->assertRedirect();
    }

    private function base(): string
    {
        return '/m/municipio-a/escolas/'.self::ESCOLA_A1;
    }

    private function gestor(): string
    {
        return $this->base().'/servidores/'.$this->ids['director'].'/censo';
    }

    private function atribuicao(): string
    {
        return $this->base().'/turmas/'.$this->ids['class'].'/atribuicoes/'.$this->ids['assignment'].'/censo';
    }

    public function test_grava_vinculos_sem_inferir_disciplina_e_audita_campos(): void
    {
        $this->login('school_secretary', [self::ESCOLA_A1]);
        $this->get($this->gestor())->assertOk()->assertSee('Gestão escolar');
        $this->get($this->base().'/servidores/'.$this->ids['teacher'].'/censo')->assertOk()->assertDontSee('Salvar gestão escolar');
        $this->get($this->atribuicao())->assertOk()->assertSee('Disciplina cadastrada, apenas como sugestão')->assertSee('name="components[]" value="3"', false)->assertDontSee('name="components[]" value="5"', false);
        $this->put($this->gestor().'/gestao', ['position' => '1', 'access_criterion' => '2', 'employment_status' => '1'])->assertRedirect($this->gestor());
        $this->put($this->atribuicao(), ['class_function' => '1', 'employment_status' => '1', 'components' => ['3']])->assertRedirect($this->atribuicao());
        $this->tenant(function (): void {
            $this->assertSame(1, StaffManagerCensus::query()->sole()->position);
            $this->assertSame([3], ClassTeacherAssignmentCensus::query()->sole()->components);
            foreach (['staff_manager_census.created', 'class_teacher_assignment_census.created'] as $action) {
                $log = AuditLog::query()->where('action', $action)->sole();
                $this->assertArrayHasKey('changed_fields', $log->after);
                $this->assertArrayNotHasKey('employment_status', $log->after);
            }
        });
        $this->get($this->base().'/censo/servidores')->assertOk()->assertSee('Docente Fictício')->assertSee('Diretora Fictícia')->assertSee('Pendente');
    }

    public function test_recusa_componentes_e_criterios_invalidos(): void
    {
        $this->login('school_secretary', [self::ESCOLA_A1]);
        $this->put($this->gestor().'/gestao', ['position' => '1', 'access_criterion' => '1', 'employment_status' => '1'])->assertSessionHasErrors('access_criterion');
        $this->put($this->atribuicao(), ['class_function' => '1', 'employment_status' => '1', 'components' => ['5']])->assertSessionHasErrors('components');
        $this->put($this->base().'/servidores/'.$this->ids['teacher'].'/censo/gestao', ['position' => '2'])->assertForbidden();
        $this->assertSame(0, $this->tenant(fn () => StaffManagerCensus::query()->count() + ClassTeacherAssignmentCensus::query()->count()));
    }

    public function test_acesso_por_escola_e_segundo_fator(): void
    {
        foreach ([['teacher', [self::ESCOLA_A1]], ['school_secretary', [self::ESCOLA_A2]]] as [$role, $schools]) {
            $this->login($role, $schools);
            $this->get($this->atribuicao())->assertForbidden();
            $this->put($this->gestor().'/gestao', ['position' => '2'])->assertForbidden();
        }
        $this->login('school_secretary', [self::ESCOLA_A1], false);
        $this->get($this->atribuicao())->assertRedirect();
        $this->put($this->atribuicao(), ['class_function' => '1'])->assertForbidden();
        $this->put($this->gestor().'/gestao', ['position' => '2'])->assertForbidden();
    }
}
