<?php

namespace Tests\Feature\Meals;

use App\Models\Tenant\AuditLog;
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
}
