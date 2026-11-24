<?php

namespace Tests\Feature\Network;

use App\Models\Tenant\ActiveSearchCase;
use App\Models\Tenant\AttendanceMark;
use App\Models\Tenant\AttendanceSession;
use App\Models\Tenant\IntegralFundebExpense;
use App\Models\Tenant\IntegralFundebFinance;
use App\Models\Tenant\IntegralPactTarget;
use App\Models\Tenant\LiteracyAssessment;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolMealServing;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\VaarCondition;
use App\Models\Tenant\WaitlistRequest;
use App\Reports\MonthlySecretariatReport;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class MonthlySecretariatReportTest extends TwoMunicipalityTestCase
{
    private const URL = '/m/municipio-a/relatorio-mensal-secretaria?mes=2027-04';

    private string $yearId;

    private string $classId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2027-05-15 14:00:00'));
        $this->tenant(function (): void {
            $actor = $this->auditFixtureUser->id;
            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(), 'year' => 2027,
                'start_date' => '2027-02-01', 'end_date' => '2027-12-18', 'status' => 'active',
            ]);
            $this->yearId = $year->id;
            $class = SchoolClass::query()->create([
                'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id,
                'name' => '2º Ano A', 'stage' => 'Ensino Fundamental', 'modality' => 'Regular',
                'shift' => 'Manhã', 'status' => 'active', 'daily_hours' => 7,
            ]);
            $this->classId = $class->id;
            $entries = [];
            foreach (range(1, 7) as $number) {
                $entries[] = $this->enroll($number, $number === 7);
            }
            foreach ([
                '2027-04-05' => ['present', 'absent'],
                '2027-04-06' => ['absent', 'justified'],
                '2027-05-03' => ['present', 'present'],
            ] as $day => $statuses) {
                $session = AttendanceSession::query()->create([
                    'id' => (string) Str::uuid(), 'school_class_id' => $class->id,
                    'attendance_date' => $day, 'recorded_by' => $actor, 'recorded_at' => now(),
                ]);
                foreach ($statuses as $index => $status) {
                    AttendanceMark::query()->create([
                        'id' => (string) Str::uuid(), 'attendance_session_id' => $session->id,
                        'student_id' => $entries[$index]->student_id, 'status' => $status,
                    ]);
                }
            }
            foreach ([
                ['2027-04-06', 80], ['2027-05-04', 90],
            ] as [$day, $quantity]) {
                SchoolMealServing::query()->create([
                    'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1,
                    'school_year_id' => $year->id, 'meal_date' => $day, 'stage' => 'fundamental',
                    'meal_type' => 'almoco', 'quantity' => $quantity,
                    'suggested_present' => 999, 'confirmed_by' => $actor, 'confirmed_at' => now(),
                ]);
            }
            ActiveSearchCase::query()->create([
                'id' => (string) Str::uuid(), 'enrollment_id' => $entries[0]->id,
                'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id,
                'student_id' => $entries[0]->student_id, 'status' => 'returned',
                'reason_category' => 'health', 'opened_at' => '2027-04-07 08:00:00',
                'returned_at' => '2027-04-09 08:00:00', 'updated_by' => $actor,
            ]);
            LiteracyAssessment::query()->create([
                'id' => (string) Str::uuid(), 'school_class_id' => $class->id,
                'student_enrollment_id' => $entries[0]->id, 'term' => 4,
                'writing' => 'alphabetic', 'reading' => 'fluent_texts',
                'recorded_by' => $actor, 'updated_by' => $actor,
            ]);
            IntegralPactTarget::query()->create([
                'id' => (string) Str::uuid(), 'school_year_id' => $year->id,
                'stage' => 'fundamental_iniciais', 'target_enrollments' => 10, 'updated_by' => $actor,
            ]);
            IntegralFundebFinance::query()->create([
                'id' => (string) Str::uuid(), 'school_year_id' => $year->id,
                'revenue_cents' => 10000000, 'updated_by' => $actor,
            ]);
            IntegralFundebExpense::query()->create([
                'id' => (string) Str::uuid(), 'school_year_id' => $year->id,
                'description' => 'Despesa institucional fictícia', 'amount_cents' => 150000, 'created_by' => $actor,
            ]);
            VaarCondition::query()->create([
                'id' => (string) Str::uuid(), 'reference_year' => 2027, 'condition_number' => 1,
                'status' => 'cumprida', 'responsible' => 'Responsável Fictício VAAR', 'updated_by' => $actor,
            ]);
            foreach ([1, 2] as $number) {
                $request = WaitlistRequest::query()->create([
                    'id' => (string) Str::uuid(), 'protocol' => 'ABCDE2345'.$number,
                    'child_name' => 'Criança Fictícia Fila '.$number, 'birth_date' => '2024-09-01',
                    'guardian_name' => 'Responsável Fictício Fila', 'guardian_phone' => '11987654321',
                    'postal_code' => '01001-000', 'address_street' => 'Rua Fictícia', 'address_number' => '10',
                    'address_district' => 'Centro', 'address_city' => 'Monte Sereno', 'address_state' => 'SP',
                    'intake_year' => 2027, 'cutoff_date' => '2027-03-31', 'stage' => 'maternal_1',
                    'desired_shift' => 'manha', 'priority_criteria' => [], 'priority_count' => 0,
                    'status' => 'atendido', 'requested_at' => '2027-04-01 08:00:00', 'created_by' => $actor,
                ]);
                DB::connection('tenant')->table('waitlist_status_changes')->insert([
                    'id' => (string) Str::uuid(), 'waitlist_request_id' => $request->id,
                    'previous_status' => 'aguardando', 'new_status' => 'atendido',
                    'reason' => 'Atendimento fictício', 'changed_by' => $actor,
                    'changed_at' => $number === 1 ? '2027-04-10 09:00:00' : '2027-05-10 09:00:00',
                ]);
            }
        });
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function enroll(int $number, bool $ended = false): StudentEnrollment
    {
        $student = Student::query()->create([
            'id' => (string) Str::uuid(), 'full_name' => 'Estudante Fictício Relatório '.$number,
            'birth_date' => '2019-02-01', 'race_color' => $number === 1 ? 'preta' : 'branca',
        ]);

        return StudentEnrollment::query()->create([
            'id' => (string) Str::uuid(), 'student_id' => $student->id,
            'school_id' => self::ESCOLA_A1, 'school_year_id' => $this->yearId,
            'school_class_id' => $this->classId, 'status' => $ended ? 'completed' : 'active',
            'started_at' => '2027-04-01', 'ended_at' => $ended ? '2027-04-14' : null,
        ]);
    }

    private function tenant(callable $callback): mixed
    {
        return app(TenantContext::class)->runFor($this->municipioA, $callback);
    }

    private function login(string $role, array $schools = []): void
    {
        $user = $this->criarUsuario(Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schools);
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at($secret, now()->getTimestamp()),
        ])->assertRedirect();
    }

    public function test_relatorio_mensal_consolida_modulos_e_nao_expoe_nomes(): void
    {
        $this->login('municipal_manager');
        $response = $this->get(self::URL)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Imprimir / salvar PDF A4')->assertSee('Equidade e VAAR')
            ->assertDontSee('Estudante Fictício Relatório')
            ->assertDontSee('Criança Fictícia Fila')->assertDontSee('Responsável Fictício VAAR')
            ->assertDontSee('Responsável Fictício Fila');
        $data = $response->viewData('data');
        $this->assertSame(6, $data['matriculas']['active']);
        $this->assertSame(7, $data['matriculas']['entries']);
        $this->assertSame(1, $data['matriculas']['exits']);
        $this->assertSame(4, $data['attendance']['recorded']);
        $this->assertSame(1, $data['attendance']['present']);
        $this->assertSame(25.0, $data['attendance']['percentage']);
        $this->assertSame(1, $data['search']['opened']);
        $this->assertSame(1, $data['search']['returns']);
        $this->assertSame(80, $data['meals']['total']);
        $this->assertSame(6, $data['integral']['total']);
        $this->assertSame(10, $data['integral']['stages']['fundamental_iniciais']['target']);
        $this->assertSame(400000, $data['integral']['minimum']);
        $this->assertSame(250000, $data['integral']['remaining']);
        $this->assertSame(1, $data['literacy']['second']['assessed']);
        $this->assertSame(100.0, $data['literacy']['second']['alphabetic_pct']);
        $this->assertSame(['waiting' => 1, 'served' => 1], $data['waitlist']);
        $race = collect($data['equity']['groups']['raca']['rows'])->keyBy('label');
        $this->assertTrue($race['Preta']['suppressed']);
        $this->assertTrue($race['Branca']['suppressed']);
        $this->assertNull($race['Branca']['metrics']);
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('Estudante Fictício', $json);
        $this->assertStringNotContainsString('Criança Fictícia', $json);
        $this->assertStringNotContainsString('Responsável Fictício', $json);
        $this->assertStringNotContainsString('reason_category', $json);
        $this->assertStringNotContainsString('student_id', $json);
    }

    public function test_exclusivo_da_gestao_municipal_e_competencia_valida(): void
    {
        foreach (['school_director', 'school_secretary', 'teacher'] as $role) {
            $this->login($role, [self::ESCOLA_A1]);
            $this->get(self::URL)->assertForbidden();
        }
        $this->login('municipal_manager');
        $this->get('/m/municipio-a/relatorio-mensal-secretaria?mes=2027-06')->assertSessionHasErrors('mes');
        $this->get('/m/municipio-a/relatorio-mensal-secretaria?mes=2027-99')->assertSessionHasErrors('mes');
        $this->get('/m/municipio-a/relatorio-mensal-secretaria?mes=2026-04')->assertOk()->assertSee('Ano letivo não cadastrado');
    }

    public function test_numero_de_consultas_nao_cresce_com_matriculas_na_mesma_escola(): void
    {
        $this->tenant(function (): void {
            $year = SchoolYear::query()->findOrFail($this->yearId);
            $month = Carbon::parse('2027-04-01');
            $report = app(MonthlySecretariatReport::class);
            $connection = DB::connection('tenant');
            $connection->enableQueryLog();
            try {
                $connection->flushQueryLog();
                $report->forMonth($year, $month);
                $before = count($connection->getQueryLog());
                foreach (range(8, 14) as $number) {
                    $this->enroll($number);
                }
                $connection->flushQueryLog();
                $report->forMonth($year, $month);
                $this->assertSame($before, count($connection->getQueryLog()));
            } finally {
                $connection->disableQueryLog();
                $connection->flushQueryLog();
            }
        });
    }
}
