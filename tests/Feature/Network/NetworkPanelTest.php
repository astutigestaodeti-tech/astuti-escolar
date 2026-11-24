<?php

namespace Tests\Feature\Network;

use App\Models\Tenant\AttendanceMark;
use App\Models\Tenant\AttendanceSession;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolCalendarDay;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\User;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class NetworkPanelTest extends TwoMunicipalityTestCase
{
    private const ESCOLA_A3 = '44444444-4444-4444-8444-444444444444';

    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private string $yearId;

    private string $classA;

    private string $classB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2027-04-15 15:00:00'));

        [$this->yearId, $this->classA, $this->classB] = app(TenantContext::class)->runFor($this->municipioA, function (): array {
            School::query()->create(['id' => self::ESCOLA_A3, 'name' => 'Zênite Fictícia', 'status' => 'active']);
            School::query()->findOrFail(self::ESCOLA_A2)->forceFill(['latitude' => -23.1000, 'longitude' => -46.2000])->save();

            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(), 'year' => 2027,
                'start_date' => '2027-02-01', 'end_date' => '2027-05-31', 'status' => 'active',
            ]);
            $classA = SchoolClass::query()->create([
                'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id,
                'name' => '3º Ano A', 'stage' => 'Ensino Fundamental', 'modality' => 'Regular',
                'shift' => 'Manhã', 'status' => 'active',
            ]);
            $classB = SchoolClass::query()->create([
                'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A2, 'school_year_id' => $year->id,
                'name' => '4º Ano B', 'stage' => 'Ensino Fundamental', 'modality' => 'Regular',
                'shift' => 'Tarde', 'status' => 'active',
            ]);
            SchoolCalendarDay::query()->create([
                'id' => (string) Str::uuid(), 'school_year_id' => $year->id, 'school_id' => self::ESCOLA_A1,
                'date' => '2027-03-01', 'kind' => 'holiday', 'description' => 'Feriado fictício da unidade',
            ]);

            return [$year->id, $classA->id, $classB->id];
        });

        $alertStudent = $this->student('Alice Estudante Fictícia', self::ESCOLA_A1, $this->classA, true, null);
        $this->student('Bruno Estudante Fictício', self::ESCOLA_A1, $this->classA, true, 'Rota Azul');
        $this->student('Carla Estudante Fictícia', self::ESCOLA_A2, $this->classB, true, 'Rota Verde');
        $this->student('Davi Ex-Aluno Fictício', self::ESCOLA_A2, $this->classB, false, 'Rota Verde');

        app(TenantContext::class)->runFor($this->municipioA, function () use ($alertStudent): void {
            foreach ([
                '2027-04-01' => 'present',
                '2027-04-12' => 'absent',
                '2027-04-13' => 'absent',
                '2027-04-14' => 'absent',
            ] as $day => $status) {
                $session = AttendanceSession::query()->create([
                    'id' => (string) Str::uuid(), 'school_class_id' => $this->classA,
                    'attendance_date' => $day, 'recorded_by' => $this->auditFixtureUser->id, 'recorded_at' => now(),
                ]);
                AttendanceMark::query()->create([
                    'id' => (string) Str::uuid(), 'attendance_session_id' => $session->id,
                    'student_id' => $alertStudent, 'status' => $status,
                ]);
            }
        });
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function student(string $name, string $school, string $class, bool $active, ?string $route): string
    {
        return app(TenantContext::class)->runFor($this->municipioA, function () use ($name, $school, $class, $active, $route): string {
            $student = Student::query()->create([
                'id' => (string) Str::uuid(), 'full_name' => $name,
            ]);
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $student->id, 'school_id' => $school,
                'school_year_id' => $this->yearId, 'school_class_id' => $class,
                'status' => $active ? 'active' : 'completed',
                'transport_status' => 'active', 'transport_route' => $route,
            ]);

            return $student->id;
        });
    }

    private function login(string $role, array $schools = [self::ESCOLA_A1], bool $municipalityB = false): User
    {
        $municipality = $municipalityB ? $this->municipioB : $this->municipioA;
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $municipality, $role, $schools);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp()),
        ])->assertRedirect();

        return $user;
    }

    private function url(): string
    {
        return '/m/municipio-a/painel-da-rede';
    }

    public function test_somente_gestao_municipal_acessa_painel_e_links(): void
    {
        $this->get($this->url())->assertRedirect('/login');

        foreach (['school_director', 'school_secretary', 'teacher'] as $role) {
            $this->login($role);
            $this->get($this->url())->assertForbidden();
            $this->get('/m/municipio-a/escolas')->assertOk()->assertDontSee('Abrir painel da rede');
        }

        $this->login('municipal_manager', [], true);
        $this->get($this->url())->assertForbidden();
        $this->login('municipal_manager', []);
        $this->get($this->url())->assertOk()->assertSee('Painel da rede');
        $this->get('/m/municipio-a/escolas')->assertOk()->assertSee('Abrir painel da rede');
    }

    public function test_tres_escolas_totais_links_ordenacao_e_somente_numeros_agregados(): void
    {
        $this->login('municipal_manager', []);
        $response = $this->get($this->url())->assertOk()
            ->assertSee('Painel da rede')
            ->assertSee('abaixo de 200')
            ->assertSee('Zênite Fictícia')
            ->assertSee('Não marcada')
            ->assertSee('Marcada');

        $totals = $response->viewData('totals');
        $this->assertSame(3, $totals['schools']);
        $this->assertSame(3, $totals['students']);
        $this->assertSame(2, $totals['classes']);
        $this->assertSame(1, $totals['alerts']);
        $this->assertSame(3, $totals['pendencies']);
        $this->assertSame(3, $totals['transport']);
        $this->assertSame(1, $totals['without_route']);
        $this->assertSame(3, $totals['calendar_below_minimum']);
        $this->assertSame(2, $totals['unmapped']);

        foreach (['Alice Estudante Fictícia', 'Bruno Estudante Fictício', 'Carla Estudante Fictícia', 'Davi Ex-Aluno Fictício'] as $name) {
            $response->assertDontSee($name);
        }
        $response->assertSee('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alertas-faltas')
            ->assertSee('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/pendencias')
            ->assertSee('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/transporte/rotas')
            ->assertSee('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/calendario')
            ->assertSee('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/turmas');

        $response->assertSeeInOrder(['>Unidade A</a>', '>Outra Unidade A</a>', '>Zênite Fictícia</a>'], false);
        $this->get($this->url().'?ordenar=nome')->assertOk()
            ->assertSeeInOrder(['>Outra Unidade A</a>', '>Unidade A</a>', '>Zênite Fictícia</a>'], false);
        $this->get($this->url().'?ordenar=pendencias')->assertOk()
            ->assertSeeInOrder(['>Unidade A</a>', '>Outra Unidade A</a>', '>Zênite Fictícia</a>'], false);
        $this->get($this->url().'?ordenar=invalido')->assertSessionHasErrors('ordenar');
    }

    public function test_agregados_para_tres_escolas_nao_criam_n_mais_um_por_aluno(): void
    {
        $this->login('municipal_manager', []);
        $queries = [];
        DB::listen(function (QueryExecuted $event) use (&$queries): void {
            if ($event->connectionName === 'tenant') {
                $queries[] = $event->sql;
            }
        });
        $this->get($this->url())->assertOk();
        $baseline = count($queries);
        for ($i = 1; $i <= 18; $i++) {
            $this->student('Estudante Fictício '.str_pad((string) $i, 2, '0', STR_PAD_LEFT), self::ESCOLA_A1, $this->classA, true, 'Rota Azul');
        }
        $queries = [];
        $this->get($this->url())->assertOk()->assertSee('21');
        $this->assertLessThanOrEqual($baseline, count($queries), 'A quantidade de consultas cresceu por aluno.');
        $this->assertLessThanOrEqual(90, $baseline);
    }
}
