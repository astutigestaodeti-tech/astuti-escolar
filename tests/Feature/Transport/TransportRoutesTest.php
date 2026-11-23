<?php

namespace Tests\Feature\Transport;

use App\Models\Tenant\Guardian;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentGuardian;
use App\Models\Tenant\StudentHealthAlert;
use App\Models\User;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class TransportRoutesTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private string $morningClass;

    private string $afternoonClass;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->morningClass, $this->afternoonClass] = app(TenantContext::class)->runFor($this->municipioA, function (): array {
            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(), 'year' => 2027,
                'start_date' => '2027-02-01', 'end_date' => '2027-12-15', 'status' => 'active',
            ]);
            $morning = SchoolClass::query()->create([
                'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1,
                'school_year_id' => $year->id, 'name' => '3º Ano A',
                'stage' => 'Ensino Fundamental', 'modality' => 'Regular', 'shift' => 'Manhã', 'status' => 'active',
            ]);
            $afternoon = SchoolClass::query()->create([
                'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1,
                'school_year_id' => $year->id, 'name' => '4º Ano B',
                'stage' => 'Ensino Fundamental', 'modality' => 'Regular', 'shift' => 'Tarde', 'status' => 'active',
            ]);

            return [$morning->id, $afternoon->id];
        });

        $this->seedStudent('Érica Fictícia', 'Rota Árvore', 'Beco do Sol');
        $this->seedStudent('Bruno Fictício', 'Rota Árvore', 'Avenida Central');
        $first = $this->seedStudent('Álvaro Fictício', 'Rota Árvore', 'Avenida Central', 'Manhã', 'active', 'active', self::ESCOLA_A1, true);
        $this->seedStudent('Carla Fictícia', null, 'Ponto 2', 'Tarde');
        $this->seedStudent('Danilo Fictício', 'Rota Bairro', 'Ponto 1', 'Tarde');
        $this->seedStudent('Elisa Fictícia', 'Rota Bairro', 'Ponto 1', 'Manhã', 'active', 'cancelled');
        $this->seedStudent('Fábio Fictício', 'Rota Bairro', 'Ponto 1', 'Manhã', 'completed');
        $this->seedStudent('Gisele Fictícia', 'Rota Bairro', 'Ponto 1', 'Manhã', 'active', 'active', self::ESCOLA_A2);
        $this->seedStudent('Hugo Fictício', 'Rota Bairro', 'Ponto 1', 'Manhã', 'active', 'not_requested');

        app(TenantContext::class)->runFor($this->municipioA, function () use ($first): void {
            $guardian = Guardian::query()->create([
                'id' => (string) Str::uuid(), 'full_name' => 'Marta Fictícia Souza', 'phone' => '11987654321',
                'cpf' => '123.456.789-09',
            ]);
            StudentGuardian::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $first, 'guardian_id' => $guardian->id,
                'relationship' => 'mother', 'is_primary_contact' => true,
            ]);
            StudentHealthAlert::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $first,
                'kind' => 'allergy', 'severity' => 'critical',
                'alert_text' => 'Alerta Fictício', 'care_instructions' => 'Contato com direção',
            ]);
        });
    }

    private function seedStudent(
        string $name,
        ?string $route,
        ?string $stop,
        string $shift = 'Manhã',
        string $status = 'active',
        string $transport = 'active',
        string $school = self::ESCOLA_A1,
        bool $adapted = false,
    ): string {
        return app(TenantContext::class)->runFor($this->municipioA, function () use ($name, $route, $stop, $shift, $status, $transport, $school, $adapted): string {
            $student = Student::query()->create([
                'id' => (string) Str::uuid(), 'full_name' => $name, 'birth_date' => '2018-05-16',
                'ra' => (string) random_int(10000000, 99999999).'-1/SP',
                'address_street' => 'Rua Privada Fictícia',
            ]);
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(), 'school_id' => $school, 'student_id' => $student->id,
                'school_year_id' => $this->classYearId($shift),
                'school_class_id' => $school === self::ESCOLA_A1 ? ($shift === 'Tarde' ? $this->afternoonClass : $this->morningClass) : null,
                'status' => $status, 'transport_status' => $transport,
                'transport_route' => $route, 'transport_stop' => $stop,
                'transport_adapted_vehicle' => $adapted,
            ]);

            return $student->id;
        });
    }

    private function classYearId(string $shift): string
    {
        return SchoolClass::query()->findOrFail($shift === 'Tarde' ? $this->afternoonClass : $this->morningClass)->school_year_id;
    }

    private function user(string $role, array $schools): User
    {
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schools);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp()),
        ])->assertRedirect();

        return $user;
    }

    private function url(string $school = self::ESCOLA_A1): string
    {
        return '/m/municipio-a/escolas/'.$school.'/transporte/rotas';
    }

    public function test_gestao_consulta_lista_ordenada_e_pronta_para_imprimir_sem_dados_excessivos(): void
    {
        $this->user('school_secretary', [self::ESCOLA_A1]);
        $response = $this->get($this->url())->assertOk()
            ->assertSee('Transporte por rota')->assertSee('5 alunos')
            ->assertSee('Rota não informada')->assertSee('Pendência:')
            ->assertSee('Marta Fictícia Souza')->assertSee('(11) 98765-4321')
            ->assertSee('Veículo adaptado')->assertSee('Sim')
            ->assertSee('Observação do motorista')->assertSee('Responsável pela entrega')
            ->assertSee('Uso restrito ao transporte escolar. Não compartilhe nem fotografe.')
            ->assertSee('transporte-rotas.js')->assertSee('transporte-rotas.css')
            ->assertDontSee('Rua Privada Fictícia')->assertDontSee('123.456.789-09')
            ->assertDontSee('2018-05-16')->assertDontSee('Alerta Fictício')
            ->assertDontSee('Contato com direção')->assertDontSee('Fábio Fictício')
            ->assertDontSee('Elisa Fictícia')->assertDontSee('Gisele Fictícia')
            ->assertDontSee('Hugo Fictício');

        $response->assertSeeInOrder(['Álvaro Fictício', 'Bruno Fictício', 'Érica Fictícia']);
        $response->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_filtros_por_rota_e_turno_e_grupo_sem_rota(): void
    {
        $this->user('school_director', [self::ESCOLA_A1]);
        $this->get($this->url().'?rota='.rawurlencode('Rota Árvore').'&turno='.rawurlencode('Manhã'))->assertOk()
            ->assertSee('3 alunos')->assertSee('Érica Fictícia')->assertDontSee('Carla Fictícia')
            ->assertDontSee('Danilo Fictício');

        $this->get($this->url().'?rota=__sem_rota__')->assertOk()->assertSee('Carla Fictícia')
            ->assertSee('1 aluno')->assertDontSee('Álvaro Fictício');

        $this->get($this->url().'?turno=Tarde')->assertOk()->assertSee('Carla Fictícia')
            ->assertSee('Danilo Fictício')->assertDontSee('Érica Fictícia');

        $this->get($this->url().'?rota=Inexistente')->assertOk()->assertSee('Nenhum aluno encontrado');
        $this->get($this->url().'?rota='.str_repeat('x', 121))->assertSessionHasErrors('rota');
    }

    public function test_lista_restrita_a_gestao_da_unidade_e_nao_publica(): void
    {
        $this->get($this->url())->assertRedirect(route('login'));

        $this->user('teacher', [self::ESCOLA_A1]);
        $this->get($this->url())->assertForbidden();

        $this->user('school_director', [self::ESCOLA_A2]);
        $this->get($this->url())->assertForbidden();

        $this->user('school_secretary', [self::ESCOLA_A1]);
        $this->get($this->url(self::ESCOLA_A2))->assertForbidden();

        $this->get('/m/municipio-b/escolas/'.self::ESCOLA_B1.'/transporte/rotas')->assertStatus(403);
    }

    public function test_consulta_em_lote_nao_faz_n_mais_um_quando_ha_muitos_alunos(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->seedStudent('Estudante Fictício '.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'Rota Bairro', 'Ponto 3');
        }
        $this->user('school_director', [self::ESCOLA_A1]);

        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            if ($query->connectionName === 'tenant') {
                $queries[] = $query->sql;
            }
        });

        $this->get($this->url())->assertOk()->assertSee('25 alunos')
            ->assertSee('Estudante Fictício 20');
        $this->assertLessThanOrEqual(9, count($queries), 'A listagem realizou consultas por aluno (N+1).');
    }

    public function test_impressao_usa_css_a4_e_javascript_externo_sem_script_inline(): void
    {
        $css = file_get_contents(public_path('css/transporte-rotas.css'));
        $script = file_get_contents(public_path('js/transporte-rotas.js'));
        $this->assertStringContainsString('@media print', $css);
        $this->assertStringContainsString('size:A4 portrait', $css);
        $this->assertStringContainsString('break-after:page', $css);
        $this->assertStringContainsString('window.print()', $script);
        $this->assertStringNotContainsString('<script>', $script);
    }
}
