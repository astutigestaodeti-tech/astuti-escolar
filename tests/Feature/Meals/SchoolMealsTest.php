<?php

namespace Tests\Feature\Meals;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\AttendanceSession;
use App\Models\Tenant\AttendanceMark;
use App\Models\Tenant\StudentHealthAlert;
use App\Meals\SchoolMeals;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolMealMenu;
use App\Models\Tenant\SchoolMealServing;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class SchoolMealsTest extends TwoMunicipalityTestCase
{
    private string $yearId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2027-10-14 14:00:00'));
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
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
            $student = Student::query()->create([
                'id' => (string) Str::uuid(), 'full_name' => 'Estudante de teste',
                'birth_date' => '2018-04-01',
            ]);
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $student->id,
                'school_id' => self::ESCOLA_A1, 'school_class_id' => $class->id,
                'school_year_id' => $year->id, 'status' => 'active',
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

    public function test_cardapio_exige_gestao_e_tem_copia_auditada(): void
    {
        $url = '/m/municipio-a/alimentacao/cardapio';
        $this->login('teacher', [self::ESCOLA_A1]);
        $this->get($url)->assertForbidden();
        $this->login('municipal_manager');
        $this->get($url.'?ano=2027&semana=2027-10-11')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->post($url, [
            'ano_id' => $this->yearId, 'semana' => '2027-10-11',
            'dia' => 1, 'etapa' => 'fundamental',
            'refeicao' => 'almoco', 'cardapio' => 'Arroz e feijão',
        ])->assertRedirect();
        $this->post($url.'/copiar', [
            'ano_id' => $this->yearId, 'origem' => '2027-10-11', 'destino' => '2027-10-18',
        ])->assertRedirect();

        $this->tenant(function (): void {
            $this->assertSame(2, SchoolMealMenu::query()->count());
            $this->assertSame(2, AuditLog::query()
                ->whereIn('action', ['meals.menu.saved', 'meals.menu.copied'])->count());
        });
    }

    public function test_registro_e_relatorio_mensal_com_escopo_escolar(): void
    {
        $this->login('school_secretary', [self::ESCOLA_A1]);
        $url = '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alimentacao';
        $this->get($url)->assertOk();
        $this->post($url, [
            'data' => '2027-10-14', 'etapa' => 'fundamental', 'refeicao' => 'almoco',
            'quantidade' => 3,
        ])->assertRedirect();
        $this->get($url.'/relatorio?mes=2027-10')->assertOk()
            ->assertSee('Relatório mensal de alimentação')->assertDontSee('Estudante de teste');
        $this->post('/m/municipio-a/escolas/'.self::ESCOLA_A2.'/alimentacao', [
            'data' => '2027-10-14', 'etapa' => 'fundamental', 'refeicao' => 'almoco',
            'quantidade' => 3,
        ])->assertForbidden();

        $this->tenant(function (): void {
            $row = SchoolMealServing::query()->sole();
            $this->assertSame(3, $row->quantity);
            $this->assertSame(1, AuditLog::query()
                ->where('action', 'meals.serving.confirmed')->count());
        });
    }

    public function test_professor_nao_acessa_e_segundo_fator_recente_bloqueia(): void
    {
        $url = '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alimentacao';
        $this->login('teacher', [self::ESCOLA_A1]);
        $this->get($url)->assertForbidden();

        $this->login('school_director', [self::ESCOLA_A1]);
        $this->travelTo(now()->addMinutes(20));
        $this->post($url, [
            'data' => '2027-10-14', 'etapa' => 'fundamental', 'refeicao' => 'almoco',
            'quantidade' => 2,
        ])->assertRedirect(route('two-factor.challenge'));
        $this->assertSame(0, $this->tenant(fn (): int => SchoolMealServing::query()->count()));
    }

    public function test_chamada_e_apenas_sugestao_e_pode_ser_superada_pela_contagem_real(): void
    {
        $this->tenant(function (): void {
            $class = SchoolClass::query()->firstOrFail();
            $student = Student::query()->firstOrFail();
            $session = AttendanceSession::query()->create([
                'id' => (string) Str::uuid(), 'school_class_id' => $class->id,
                'attendance_date' => '2027-10-14', 'recorded_by' => $this->auditFixtureUser->id,
                'recorded_at' => now(),
            ]);
            AttendanceMark::query()->create([
                'id' => (string) Str::uuid(), 'attendance_session_id' => $session->id,
                'student_id' => $student->id, 'status' => 'present',
            ]);
            $school = \App\Models\Tenant\School::query()->findOrFail(self::ESCOLA_A1);
            $year = SchoolYear::query()->findOrFail($this->yearId);
            $this->assertSame(1, app(SchoolMeals::class)->suggested($school, $year, '2027-10-14')['fundamental']);
            $this->assertNull(app(SchoolMeals::class)->suggested($school, $year, '2027-10-13'));
        });

        $this->login('school_director', [self::ESCOLA_A1]);
        $url = '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alimentacao';
        $this->post($url, [
            'data' => '2027-10-14', 'etapa' => 'fundamental', 'refeicao' => 'almoco',
            'quantidade' => 4,
        ])->assertRedirect();
        $this->tenant(function (): void {
            $row = SchoolMealServing::query()->sole();
            $this->assertSame(1, $row->suggested_present);
            $this->assertSame(4, $row->quantity);
        });
    }

    public function test_restricoes_alimentares_so_exibem_contagens_na_cozinha(): void
    {
        $this->tenant(function (): void {
            $student = Student::query()->firstOrFail();
            StudentHealthAlert::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $student->id,
                'kind' => 'allergy', 'severity' => 'attention',
                'alert_text' => 'Alergia a amendoim', 'care_instructions' => 'Instrução reservada',
            ]);
        });
        $url = '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alimentacao';
        $this->login('municipal_manager');
        $this->get($url.'/cozinha')->assertOk()->assertSee('Amendoim')
            ->assertDontSee('Estudante de teste')->assertDontSee('Instrução reservada');
        $this->get($url.'/restricoes')->assertForbidden();

        $this->login('school_secretary', [self::ESCOLA_A1]);
        $this->get($url.'/restricoes')->assertOk()->assertSee('Estudante de teste')
            ->assertDontSee('Instrução reservada');
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A2.'/alimentacao/restricoes')
            ->assertForbidden();
    }

    public function test_datas_invalidas_nao_geram_lancamentos(): void
    {
        $this->login('school_director', [self::ESCOLA_A1]);
        $url = '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alimentacao';
        foreach (['2027-12-16', '2028-01-02'] as $date) {
            $this->post($url, [
                'data' => $date, 'etapa' => 'fundamental', 'refeicao' => 'almoco', 'quantidade' => 4,
            ])->assertSessionHasErrors();
        }
        $this->post($url, [
            'data' => '2027-10-14', 'etapa' => 'fundamental', 'refeicao' => 'almoco', 'quantidade' => -1,
        ])->assertSessionHasErrors('quantidade');
        $this->assertSame(0, $this->tenant(fn (): int => SchoolMealServing::query()->count()));
    }
}
