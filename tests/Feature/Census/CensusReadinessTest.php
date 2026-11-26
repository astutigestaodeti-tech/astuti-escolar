<?php

namespace Tests\Feature\Census;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\User;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class CensusReadinessTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private const URL = '/m/municipio-a/conferencia-do-censo';

    private string $yearId;

    /** @var array<string, string> */
    private array $classes = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-04-15 15:00:00'));

        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            School::query()->findOrFail(self::ESCOLA_A1)->forceFill(['inep_code' => '35999001'])->save();
            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(), 'year' => 2026,
                'start_date' => '2026-02-01', 'end_date' => '2026-12-15', 'status' => 'active',
            ]);
            $this->yearId = $year->id;
            foreach ([
                'creche_integral' => [self::ESCOLA_A1, 'Berçário I', 'Educação Infantil', 'Integral'],
                'fund_manha' => [self::ESCOLA_A1, '3º Ano A', 'Ensino Fundamental', 'Manhã'],
                'sem_turno' => [self::ESCOLA_A2, '2º Ano B', 'Ensino Fundamental', 'Turno'],
            ] as $key => [$school, $name, $stage, $shift]) {
                $this->classes[$key] = SchoolClass::query()->create([
                    'id' => (string) Str::uuid(), 'school_id' => $school, 'school_year_id' => $year->id,
                    'name' => $name, 'stage' => $stage, 'modality' => 'Regular', 'shift' => $shift, 'status' => 'active',
                ])->id;
            }
        });
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function aluno(string $nome, string $escola, ?string $turma, array $dados = [], ?string $transporte = 'not_requested'): string
    {
        return app(TenantContext::class)->runFor($this->municipioA, function () use ($nome, $escola, $turma, $dados, $transporte): string {
            $student = Student::query()->create([
                'id' => (string) Str::uuid(), 'full_name' => $nome, 'birth_date' => '2019-05-10',
                'race_color' => 'parda', 'residence_zone' => 'urban', ...$dados,
            ]);
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $student->id, 'school_id' => $escola,
                'school_year_id' => $this->yearId, 'school_class_id' => $turma,
                'status' => 'active', 'transport_status' => $transporte,
            ]);

            return $student->id;
        });
    }

    private function entrar(string $papel, array $escolas = []): User
    {
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $papel, $escolas);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp()),
        ])->assertRedirect();

        return $user;
    }

    public function test_conferencia_nao_mistura_matriculas_de_anos_diferentes(): void
    {
        $student = $this->aluno('Transferência Fictícia', self::ESCOLA_A1, $this->classes['fund_manha']);
        app(TenantContext::class)->runFor($this->municipioA, function () use ($student): void {
            $future = SchoolYear::query()->create([
                'id' => (string) Str::uuid(), 'year' => 2027,
                'start_date' => '2027-02-01', 'end_date' => '2027-12-15', 'status' => 'active',
            ]);
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $student,
                'school_id' => self::ESCOLA_A2, 'school_year_id' => $future->id,
                'school_class_id' => null, 'status' => 'active',
            ]);
        });

        $this->travelTo(Carbon::parse('2027-04-15 15:00:00'));
        config(['censo.vaaf_base' => 6000.0]);
        $this->entrar('municipal_manager');
        $this->get(self::URL)->assertOk()->assertViewHas('data', function (array $data): bool {
            $this->assertSame(1, $data['totals']['enrollments']);
            $this->assertSame(0, $data['byType']['duplicada']);
            $this->assertSame(0, $data['byType']['sem_turma']);
            $this->assertSame(6000.0, $data['totals']['fundeb']);
            $this->assertSame(114.0, $data['totals']['pnae']);

            return true;
        });
    }

    public function test_conferencia_isola_municipios_mesmo_com_identificador_de_ano_igual(): void
    {
        $this->aluno('Aluno Local Fictício', self::ESCOLA_A1, $this->classes['fund_manha']);
        app(TenantContext::class)->runFor($this->municipioB, function (): void {
            SchoolYear::query()->create([
                'id' => $this->yearId, 'year' => 2026,
                'start_date' => '2026-02-01', 'end_date' => '2026-12-15', 'status' => 'active',
            ]);
            $student = Student::query()->create([
                'id' => (string) Str::uuid(), 'full_name' => 'Aluno Externo Fictício',
            ]);
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $student->id,
                'school_id' => self::ESCOLA_B1, 'school_year_id' => $this->yearId,
                'status' => 'active',
            ]);
        });

        $this->entrar('municipal_manager');
        $this->get(self::URL)->assertOk()->assertDontSee('Aluno Externo Fictício')
            ->assertViewHas('data', function (array $data): bool {
                $this->assertSame(1, $data['totals']['enrollments']);
                $this->assertSame(0, $data['byType']['sem_turma']);
                $this->assertSame([self::ESCOLA_A1], $data['rows']->pluck('school_id')->all());

                return true;
            });
    }

    public function test_sem_ano_de_referencia_nao_agrega_outros_anos(): void
    {
        $this->aluno('Aluno Fictício', self::ESCOLA_A1, $this->classes['fund_manha']);
        config(['censo.year_reference' => 2025]);
        $this->entrar('municipal_manager');
        $this->get(self::URL)->assertOk()->assertViewHas('data', function (array $data): bool {
            $this->assertSame(0, $data['totals']['enrollments']);
            $this->assertSame(0, $data['byType']['duplicada']);

            return true;
        });
    }

    public function test_so_a_gestao_municipal_abre_a_conferencia(): void
    {
        $this->get(self::URL)->assertRedirect('/login');
        foreach (['school_director', 'school_secretary', 'teacher'] as $papel) {
            $this->entrar($papel, [self::ESCOLA_A1]);
            $this->get(self::URL)->assertForbidden();
        }
    }

    public function test_avisos_por_aluno_e_estimativa_por_categoria(): void
    {
        $this->aluno('Ana Completa Fictícia', self::ESCOLA_A1, $this->classes['fund_manha']);
        $this->aluno('Bia Integral Fictícia', self::ESCOLA_A1, $this->classes['creche_integral'], ['birth_date' => '2025-06-01']);
        $this->aluno('Caio Sem Turma Fictício', self::ESCOLA_A1, null);
        $this->aluno('Duda Sem Cor Fictícia', self::ESCOLA_A1, $this->classes['fund_manha'], ['race_color' => null]);
        $this->aluno('Enzo Rural Fictício', self::ESCOLA_A1, $this->classes['fund_manha'], ['residence_zone' => 'rural'], null);
        $this->aluno('Fábio Turno Fictício', self::ESCOLA_A2, $this->classes['sem_turno']);
        $duplicado = $this->aluno('Gabi Duas Escolas Fictícia', self::ESCOLA_A1, $this->classes['fund_manha']);
        app(TenantContext::class)->runFor($this->municipioA, fn () => StudentEnrollment::query()->create([
            'id' => (string) Str::uuid(), 'student_id' => $duplicado, 'school_id' => self::ESCOLA_A2,
            'school_year_id' => $this->yearId, 'school_class_id' => $this->classes['sem_turno'], 'status' => 'active', 'transport_status' => 'not_requested',
        ]));

        $this->entrar('municipal_manager');
        $resposta = $this->get(self::URL)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Conferência do Censo');

        $resposta->assertSeeInOrder(['Caio Sem Turma Fictício', 'Sem turma: a matrícula não entra no Censo'], false)
            ->assertSeeInOrder(['Duda Sem Cor Fictícia', 'Cor/raça não informada'], false)
            ->assertSeeInOrder(['Enzo Rural Fictício', 'Mora na zona rural e o transporte não foi informado'], false)
            ->assertSeeInOrder(['Fábio Turno Fictício', 'Turno da turma não indica se é integral ou parcial'], false)
            ->assertSee('Matrícula ativa em mais de uma escola')
            ->assertDontSee('Ana Completa Fictícia')
            ->assertDontSee('Bia Integral Fictícia');

        // Creche integral: 1 matrícula, peso 1,55, merenda 200 x R$ 1,57.
        $resposta->assertSeeInOrder(['Creche em tempo integral', '1', '1,55', '1,55', 'R$ 314,00'], false);
        // Sem turma: fora do Censo, valor da merenda em risco calculado como Fundamental parcial.
        $resposta->assertSee('R$ 114,00');
        // Sem o VAAF do estado, o Fundeb aparece só em matrículas ponderadas.
        $resposta->assertDontSee('Fundeb estimado')->assertSee('publicado pelo FNDE para o ano');
        // A escola sem INEP aparece como pendência.
        $resposta->assertSee('Não informado');

        // Tela com dados, para a conferência de margens.
        if ($dump = getenv('ASTUTI_DUMP_TELAS') ?: null) {
            $html = str_replace(rtrim((string) config('app.url'), '/').'/', 'file://'.public_path().'/', $resposta->getContent());
            file_put_contents(rtrim($dump, '/').'/conferencia_censo_com_dados.html', preg_replace('/\\.(css|js)\\?v=\\d+/', '.$1', $html));
        }

        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $log = AuditLog::query()->where('action', 'census.readiness.viewed')->latest('occurred_at')->firstOrFail();
            $this->assertNull($log->after);
        });
    }

    public function test_com_o_vaaf_informado_o_fundeb_aparece_em_reais_e_o_filtro_funciona(): void
    {
        config(['censo.vaaf_base' => 6000.0]);
        $this->aluno('Ana Completa Fictícia', self::ESCOLA_A1, $this->classes['fund_manha']);
        $this->aluno('Caio Sem Turma Fictício', self::ESCOLA_A1, null);
        $this->aluno('Duda Sem Cor Fictícia', self::ESCOLA_A2, $this->classes['sem_turno'], ['race_color' => null]);

        $this->entrar('municipal_manager');
        $this->get(self::URL)->assertOk()
            ->assertSee('Fundeb estimado')
            ->assertSee('R$ 6.000,00');

        $this->get(self::URL.'?aviso=cor_raca')->assertOk()
            ->assertSee('Duda Sem Cor Fictícia')->assertDontSee('Caio Sem Turma Fictício');
        $this->get(self::URL.'?escola='.self::ESCOLA_A1)->assertOk()
            ->assertSee('Caio Sem Turma Fictício')->assertDontSee('Duda Sem Cor Fictícia');
        $this->get(self::URL.'?aviso=inexistente')->assertSessionHasErrors('aviso');
    }

    public function test_gestao_informa_o_vaaf_do_ano_e_a_estimativa_passa_a_reais_com_auditoria(): void
    {
        $this->aluno('Ana Completa Fictícia', self::ESCOLA_A1, $this->classes['fund_manha']);

        $this->entrar('school_director', [self::ESCOLA_A1]);
        $this->post(self::URL.'/parametros', ['vaaf_base' => '6.000,00', 'source_note' => 'Portaria fictícia'])->assertForbidden();

        $this->entrar('municipal_manager');
        $this->post(self::URL.'/parametros', ['vaaf_base' => 'abc', 'source_note' => 'Portaria fictícia'])->assertSessionHasErrors('vaaf_base');
        $this->post(self::URL.'/parametros', ['vaaf_base' => '6.000,00', 'source_note' => 'Portaria fictícia do FNDE'])
            ->assertRedirect(self::URL.'#titulo-censo-estimativa');

        $this->get(self::URL)->assertOk()
            ->assertSee('Fundeb estimado')
            ->assertSee('R$ 6.000,00')
            ->assertSee('value="6.000,00"', false);

        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $log = AuditLog::query()->where('action', 'census.parameters.created')->firstOrFail();
            $this->assertSame('Portaria fictícia do FNDE', $log->after['source_note']);
        });
    }
}
