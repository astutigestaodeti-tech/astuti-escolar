<?php

namespace Tests\Feature\Integral;

use App\Census\CensusReadiness;
use App\Integral\FullTimeReadiness;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\IntegralFundebExpense;
use App\Models\Tenant\IntegralFundebFinance;
use App\Models\Tenant\IntegralPactTarget;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class FullTimeEducationTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private const URL = '/m/municipio-a/tempo-integral';

    private string $yearId;

    private string $previousId;

    private string $currentIntegral;

    private string $previousIntegral;

    private string $previousPartial;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2027-08-15 15:00:00'));

        $this->tenant(function (): void {
            $previous = SchoolYear::query()->create([
                'id' => (string) Str::uuid(), 'year' => 2026,
                'start_date' => '2026-02-01', 'end_date' => '2026-12-15', 'status' => 'completed',
            ]);
            $current = SchoolYear::query()->create([
                'id' => (string) Str::uuid(), 'year' => 2027,
                'start_date' => '2027-02-01', 'end_date' => '2027-12-15', 'status' => 'active',
            ]);
            $this->previousId = $previous->id;
            $this->yearId = $current->id;
            $this->previousIntegral = $this->room(self::ESCOLA_A1, $previous->id, '5º Ano A', 'Integral', 7.0);
            $this->previousPartial = $this->room(self::ESCOLA_A1, $previous->id, '4º Ano A', 'Manhã', 5.0);
            $this->currentIntegral = $this->room(self::ESCOLA_A1, $current->id, '5º Ano B', 'Manhã', 7.0);
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

    private function room(string $school, string $year, string $name, string $shift, ?float $hours): string
    {
        return SchoolClass::query()->create([
            'id' => (string) Str::uuid(), 'school_id' => $school, 'school_year_id' => $year,
            'name' => $name, 'stage' => 'Ensino Fundamental', 'modality' => 'Regular',
            'shift' => $shift, 'daily_hours' => $hours, 'status' => 'active',
        ])->id;
    }

    private function student(string $name, string $classId, string $yearId, string $status = 'active', ?string $studentId = null): string
    {
        return $this->tenant(function () use ($name, $classId, $yearId, $status, $studentId): string {
            $class = SchoolClass::query()->findOrFail($classId);
            $id = $studentId ?? Student::query()->create([
                'id' => (string) Str::uuid(), 'full_name' => $name,
            ])->id;
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $id,
                'school_id' => $class->school_id, 'school_class_id' => $classId,
                'school_year_id' => $yearId, 'status' => $status,
            ]);

            return $id;
        });
    }

    private function login(string $role, array $schools = []): void
    {
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schools);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp()),
        ])->assertRedirect();
    }

    public function test_jornada_de_sete_horas_prevalece_sobre_turno_livre_e_alerta_divergencia(): void
    {
        $this->assertTrue(FullTimeReadiness::integral(new SchoolClass(['shift' => 'Manhã', 'daily_hours' => 7.0])));
        $this->assertFalse(FullTimeReadiness::integral(new SchoolClass(['shift' => 'Integral', 'daily_hours' => 6.9])));
        $this->assertTrue(FullTimeReadiness::integral(new SchoolClass(['shift' => 'Integral'])));
        $this->assertTrue(FullTimeReadiness::mismatch(new SchoolClass(['shift' => 'Manhã', 'daily_hours' => 7.0])));

        $this->student('Ana Jornada Fictícia', $this->currentIntegral, $this->yearId);
        $this->login('municipal_manager');

        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/turmas')->assertOk()
            ->assertSee('Turno e jornada divergem');
        $this->get('/m/municipio-a/conferencia-do-censo')->assertOk()
            ->assertSee('Ensino Fundamental em tempo integral');

        $this->tenant(function (): void {
            $data = app(CensusReadiness::class)->forNetwork();
            $integral = collect($data['categories'])->firstWhere('key', 'fundamental_integral');
            $this->assertSame(1, $integral['count']);
        });
    }

    public function test_matriculas_atuais_e_novas_por_comparacao_com_ano_anterior_e_meta(): void
    {
        $persist = $this->student('Aluno Mantido Fictício', $this->previousIntegral, $this->previousId, 'completed');
        $converted = $this->student('Aluna Convertida Fictícia', $this->previousPartial, $this->previousId, 'completed');
        $this->student('Aluno Mantido Fictício', $this->currentIntegral, $this->yearId, 'active', $persist);
        $this->student('Aluna Convertida Fictícia', $this->currentIntegral, $this->yearId, 'active', $converted);
        $this->student('Aluno Novo Fictício', $this->currentIntegral, $this->yearId);

        $this->login('municipal_manager');
        $this->post(self::URL.'/metas', ['ano_id' => $this->yearId, 'etapa' => 'fundamental_iniciais', 'meta' => 4])
            ->assertRedirect();

        $response = $this->get(self::URL.'?ano=2027')->assertOk()
            ->assertSee('Aluno Mantido Fictício')->assertSee('Aluna Convertida Fictícia')->assertSee('Aluno Novo Fictício');

        $data = $response->viewData('data');
        $this->assertSame(3, $data['totals']['current']);
        $this->assertSame(2, $data['totals']['new']);
        $this->assertSame(1, $data['byStage']['fundamental_iniciais']['current'] - $data['totals']['new']);
        $this->assertSame(2026, $data['baselineYear']);
        $this->assertSame(4, $this->tenant(fn (): int => IntegralPactTarget::query()->firstOrFail()->target_enrollments));
        $response->assertSee('Não pactuada')->assertSee('Diferença para a meta');
    }

    public function test_4_por_cento_receita_valor_aplicado_e_pdf_privado_auditados(): void
    {
        Storage::fake('local');
        $this->login('municipal_manager');

        $this->post(self::URL.'/receita', [
            'ano_id' => $this->yearId, 'receita' => 'R$ 1.000.000,00',
        ])->assertRedirect();
        $this->post(self::URL.'/aplicacoes', [
            'ano_id' => $this->yearId, 'descricao' => 'Ampliação fictícia de refeitório',
            'valor' => '10000.25',
            'documento' => UploadedFile::fake()->create('documento.pdf', 20, 'application/pdf'),
        ])->assertRedirect();

        $response = $this->get(self::URL.'?ano=2027')->assertOk()
            ->assertSee('R$ 1.000.000,00')->assertSee('R$ 40.000,00')
            ->assertSee('R$ 10.000,25')->assertSee('R$ 29.999,75');
        $this->assertSame(100000000, $this->tenant(fn (): int => IntegralFundebFinance::query()->firstOrFail()->revenue_cents));
        $expense = $this->tenant(fn (): IntegralFundebExpense => IntegralFundebExpense::query()->firstOrFail());
        $this->assertSame(1000025, $expense->amount_cents);
        Storage::disk('local')->assertExists($expense->evidence_path);
        $this->assertStringNotContainsString('documento.pdf', $expense->evidence_path);

        $this->get(self::URL.'/aplicacoes/'.$expense->id.'/documento')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->tenant(function () use ($expense): void {
            $log = AuditLog::query()->where('action', 'integral.expense.created')->firstOrFail();
            $this->assertSame(1000025, $log->after['amount_cents']);
            $this->assertTrue($log->after['has_evidence']);
            $this->assertStringNotContainsString($expense->evidence_path, $log->toJson());
            $this->assertStringNotContainsString('documento.pdf', $log->toJson());
        });
    }

    public function test_perfis_escolas_2fa_recente_e_restricao_de_documentos(): void
    {
        $schoolUrl = '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/tempo-integral';
        $classUrl = '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/turmas/'.$this->currentIntegral.'/jornada';

        $this->get(self::URL)->assertRedirect('/login');
        $this->login('teacher', [self::ESCOLA_A1]);
        $this->get(self::URL)->assertForbidden();
        $this->get($schoolUrl)->assertForbidden();

        foreach (['school_director', 'school_secretary'] as $role) {
            $this->login($role, [self::ESCOLA_A1]);
            $this->get(self::URL)->assertForbidden();
            $this->get($schoolUrl)->assertOk()->assertDontSee('Aplicação mínima de 4%');
            $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A2.'/tempo-integral')->assertForbidden();
            $this->post($classUrl, ['daily_hours' => '8.0'])->assertForbidden();
            $this->post(self::URL.'/metas', ['ano_id' => $this->yearId, 'etapa' => 'creche', 'meta' => 2])
                ->assertForbidden();
        }

        $this->login('municipal_manager');
        $this->post($classUrl, ['daily_hours' => '8.0'])->assertRedirect();
        $this->assertSame('8.0', $this->tenant(fn () => SchoolClass::query()->findOrFail($this->currentIntegral)->daily_hours));
        $this->travelTo(now()->addMinutes(20));
        $this->post(self::URL.'/receita', ['ano_id' => $this->yearId, 'receita' => '100.00'])
            ->assertRedirect(route('two-factor.challenge'));
        $this->assertSame(0, $this->tenant(fn (): int => IntegralFundebFinance::query()->count()));
    }

    public function test_erro_de_jornada_e_valor_nao_e_gravado(): void
    {
        $this->login('municipal_manager');
        $url = '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/turmas/'.$this->currentIntegral.'/jornada';
        $this->post($url, ['daily_hours' => '16.5'])->assertSessionHasErrors('daily_hours');
        $this->post(self::URL.'/aplicacoes', [
            'ano_id' => $this->yearId, 'descricao' => 'Teste fictício de aplicação',
            'valor' => '0.00',
        ])->assertSessionHasErrors('valor');
        $this->assertSame(0, $this->tenant(fn (): int => IntegralFundebExpense::query()->count()));
    }
}
