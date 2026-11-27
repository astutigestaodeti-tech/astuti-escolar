<?php

namespace Tests\Feature\Attendance;

use App\Attendance\SistemaPresenca;
use App\Models\Tenant\AttendanceMark;
use App\Models\Tenant\AttendanceSession;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\PresenceRegistration;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class SistemaPresencaTest extends TwoMunicipalityTestCase
{
    /** @var array<string, string> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-04-06 10:00:00'));
        $this->ids = $this->tenant(function (): array {
            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(), 'year' => 2026, 'start_date' => '2026-02-02', 'end_date' => '2026-12-15', 'status' => 'active',
            ]);
            $class = SchoolClass::query()->create([
                'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id,
                'name' => '3º Ano A', 'stage' => 'Ensino Fundamental', 'modality' => 'Regular', 'shift' => 'Manhã', 'status' => 'active',
            ]);
            $alunos = [];
            foreach (['ana' => ['Ana Fictícia Souza', '2018-03-01'], 'pre' => ['Pedro Fictício Lima', '2021-01-10'], 'bebe' => ['Bebê Fictício Rocha', '2023-05-01']] as $chave => [$nome, $nascimento]) {
                $aluno = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => $nome, 'birth_date' => $nascimento]);
                StudentEnrollment::query()->create([
                    'id' => (string) Str::uuid(), 'student_id' => $aluno->id, 'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id,
                    'school_class_id' => $class->id, 'status' => 'active', 'started_at' => '2026-02-02',
                ]);
                $alunos[$chave] = $aluno->id;
            }
            // Fevereiro: 4 chamadas; março: 5 chamadas.
            $marcas = [
                'ana' => ['2026-02' => ['present', 'present', 'present', 'absent'], '2026-03' => ['present', 'present', 'absent', 'justified', 'absent']],
                'pre' => ['2026-02' => ['present', 'present', 'absent', 'absent'], '2026-03' => ['present', 'present', 'present', 'absent', 'absent']],
                'bebe' => ['2026-02' => ['absent', 'absent', 'absent', 'absent'], '2026-03' => ['absent', 'absent', 'absent', 'absent', 'absent']],
            ];
            foreach (['2026-02' => [9, 10, 11, 12], '2026-03' => [2, 3, 4, 5, 6]] as $mes => $dias) {
                foreach ($dias as $i => $dia) {
                    $sessao = AttendanceSession::query()->create([
                        'id' => (string) Str::uuid(), 'school_class_id' => $class->id, 'attendance_date' => sprintf('%s-%02d', $mes, $dia),
                        'recorded_by' => (string) Str::uuid(), 'recorded_at' => now(),
                    ]);
                    foreach ($alunos as $chave => $aluno) {
                        AttendanceMark::query()->create(['id' => (string) Str::uuid(), 'attendance_session_id' => $sessao->id, 'student_id' => $aluno, 'status' => $marcas[$chave][$mes][$i]]);
                    }
                }
            }

            return ['year' => $year->id, ...$alunos];
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

    private function url(): string
    {
        return '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/sistema-presenca';
    }

    public function test_minimo_por_idade_e_periodo_do_calendario(): void
    {
        $this->assertNull(SistemaPresenca::minimo(3));
        $this->assertSame(60, SistemaPresenca::minimo(4));
        $this->assertSame(60, SistemaPresenca::minimo(5));
        $this->assertSame(75, SistemaPresenca::minimo(6));
        $this->assertSame(75, SistemaPresenca::minimo(17));
        $this->assertNull(SistemaPresenca::minimo(18));
        $this->assertSame(1, SistemaPresenca::periodoDe(CarbonImmutable::parse('2026-01-20')));
        $this->assertSame(1, SistemaPresenca::periodoDe(CarbonImmutable::parse('2026-03-31')));
        $this->assertSame(2, SistemaPresenca::periodoDe(CarbonImmutable::parse('2026-04-01')));
        $this->assertSame(5, SistemaPresenca::periodoDe(CarbonImmutable::parse('2026-12-10')));
    }

    public function test_secretaria_ve_frequencia_mensal_marca_lancado_e_auditoria_sem_beneficio(): void
    {
        $this->login('school_secretary', [self::ESCOLA_A1]);

        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1)->assertOk()->assertSee($this->url(), false);
        // Abril: o período padrão é o 2º; o 1º é escolhido pelo filtro.
        $this->get($this->url())->assertOk()->assertSee('2º período (abril e maio)');

        $pagina = $this->get($this->url().'?periodo=1')->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Ana Fictícia Souza')->assertSee('Pedro Fictício Lima')->assertDontSee('Bebê Fictício Rocha')
            ->assertSee('2 aluno(s) de 4 a 17 anos. Abaixo do mínimo: 2.');
        // Ana (8 anos, mínimo 75%): fevereiro 75%, março 40% com 1 justificada contando como falta.
        $pagina->assertSee('75%')->assertSee('40%')->assertSee('3 falta(s) em 5, 1 justificada(s)');
        // Pedro (5 anos, mínimo 60%): fevereiro 50% fica abaixo.
        $this->get($this->url().'?periodo=1&situacao=abaixo')->assertOk()->assertSee('Ana Fictícia Souza')->assertSee('Pedro Fictício Lima');
        $this->get($this->url().'?periodo=1&busca=pedro')->assertOk()->assertDontSee('Ana Fictícia Souza');

        $this->post($this->url(), ['aluno' => $this->ids['ana'], 'ano_letivo' => $this->ids['year'], 'periodo' => 1, 'lancado' => '1'])->assertRedirect();
        $this->get($this->url().'?periodo=1')->assertSee('Lançado em')->assertSee('Lançados: 1.');
        $this->post($this->url(), ['aluno' => $this->ids['ana'], 'ano_letivo' => $this->ids['year'], 'periodo' => 1, 'lancado' => '1'])->assertRedirect();
        $this->assertSame(1, $this->tenant(fn () => PresenceRegistration::query()->count()));
        $this->post($this->url(), ['aluno' => $this->ids['ana'], 'ano_letivo' => $this->ids['year'], 'periodo' => 1, 'lancado' => '0'])->assertRedirect();
        $this->assertSame(0, $this->tenant(fn () => PresenceRegistration::query()->count()));

        $logs = $this->tenant(fn () => AuditLog::query()->where('action', 'like', 'presence_registration.%')->orderBy('occurred_at')->pluck('action')->all());
        $this->assertSame(['presence_registration.marked', 'presence_registration.unmarked'], $logs);

        // Aluno de outra escola não pode ser marcado.
        $this->post($this->url(), ['aluno' => (string) Str::uuid(), 'ano_letivo' => $this->ids['year'], 'periodo' => 1, 'lancado' => '1'])->assertNotFound();
    }

    public function test_formulario_de_ano_anterior_nao_registra_frequencia_no_novo_ano(): void
    {
        $nextYear = $this->tenant(function (): SchoolYear {
            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(), 'year' => 2027, 'start_date' => '2027-02-01', 'end_date' => '2027-12-17', 'status' => 'active',
            ]);
            $class = SchoolClass::query()->create([
                'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id,
                'name' => '4º Ano A', 'stage' => 'Ensino Fundamental', 'modality' => 'Regular', 'shift' => 'Manhã', 'status' => 'active',
            ]);
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $this->ids['ana'], 'school_id' => self::ESCOLA_A1,
                'school_year_id' => $year->id, 'school_class_id' => $class->id, 'status' => 'active',
            ]);

            return $year;
        });
        $this->login('school_secretary', [self::ESCOLA_A1]);
        $page = $this->url().'?periodo=1';
        $this->get($page)->assertOk()
            ->assertSee('name="ano_letivo" value="'.$this->ids['year'].'"', false);

        $this->travelTo(Carbon::parse('2027-04-06 10:00:00'));
        $this->login('school_secretary', [self::ESCOLA_A1]);
        $payload = ['aluno' => $this->ids['ana'], 'ano_letivo' => $this->ids['year'], 'periodo' => 1, 'lancado' => '1'];
        $this->from($page)->post($this->url(), $payload)->assertRedirect($page)->assertSessionHasErrors('ano_letivo');
        $this->get($page)->assertOk()->assertSee('A página está desatualizada.')
            ->assertSee('name="ano_letivo" value="'.$nextYear->id.'"', false);
        $this->assertSame(0, $this->tenant(fn () => PresenceRegistration::query()->count()));

        $this->post($this->url(), [...$payload, 'ano_letivo' => $nextYear->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($nextYear->id, $this->tenant(fn () => PresenceRegistration::query()->sole()->school_year_id));
    }

    public function test_gestao_ve_resumo_da_rede_e_professor_nao_acessa(): void
    {
        $this->login('municipal_manager');
        $this->get('/m/municipio-a/sistema-presenca?periodo=1')->assertOk()
            ->assertSee('Sistema Presença')->assertSee('Unidade A')->assertSee('0 de 2');

        $this->login('teacher', [self::ESCOLA_A1]);
        $this->get($this->url())->assertForbidden();
        $this->get('/m/municipio-a/sistema-presenca')->assertForbidden();

        $this->login('school_secretary', [self::ESCOLA_A2]);
        $this->get($this->url())->assertForbidden();
    }
}
