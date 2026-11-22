<?php

namespace Tests\Feature\Students;

use App\Models\Platform\Municipality;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\User;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class StudentRecordWriteTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private string $yearId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->yearId = app(TenantContext::class)->runFor($this->municipioA, fn (): string => SchoolYear::query()->create([
            'id' => (string) Str::uuid(),
            'year' => 2027,
            'start_date' => '2027-02-01',
            'end_date' => '2027-12-18',
            'status' => 'active',
        ])->id);
    }

    private function conta(string $email, string $role, array $escolas = [], bool $segundoFator = true): User
    {
        $user = $this->criarUsuario($email);
        $this->vincular($user, $this->municipioA, $role, $escolas);

        if ($segundoFator) {
            $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
        }

        return $user;
    }

    /** Entra e conclui o desafio do segundo fator, como a pessoa faria. */
    private function entrarComSegundoFator(User $user): void
    {
        $this->actingAs($user)
            ->post('/seguranca/segundo-fator/desafio', ['code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp())])
            ->assertRedirect();
    }

    private function url(string $sufixo = ''): string
    {
        return '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos'.$sufixo;
    }

    private function dados(array $extra = []): array
    {
        return array_replace([
            'full_name' => '  Lívia   Fictícia  de Souza ',
            'birth_date' => '2019-05-10',
            'school_year_id' => $this->yearId,
            'started_at' => '2027-02-03',
        ], $extra);
    }

    private function noMunicipio(Municipality $municipio, callable $callback): mixed
    {
        return app(TenantContext::class)->runFor($municipio, $callback);
    }

    public function test_secretaria_com_segundo_fator_cadastra_e_matricula_com_auditoria(): void
    {
        $user = $this->conta('secretaria-grava@example.invalid', 'school_secretary', [self::ESCOLA_A1]);
        $this->entrarComSegundoFator($user);

        $response = $this->post($this->url(), $this->dados());

        [$student, $enrollment, $logs] = $this->noMunicipio($this->municipioA, function (): array {
            $student = Student::query()->where('full_name', 'Lívia Fictícia de Souza')->firstOrFail();

            return [
                $student,
                StudentEnrollment::query()->where('student_id', $student->id)->firstOrFail(),
                AuditLog::query()->whereIn('auditable_type', [Student::class, StudentEnrollment::class])->get(),
            ];
        });

        $response->assertRedirect($this->url('/'.$student->id));
        $this->assertSame('2019-05-10', $student->birth_date->format('Y-m-d'));
        $this->assertSame(self::ESCOLA_A1, $enrollment->school_id);
        $this->assertSame('active', $enrollment->status);
        $this->assertSame('2027-02-03', $enrollment->started_at->format('Y-m-d'));
        $this->assertCount(2, $logs);
        $this->assertTrue($logs->every(fn (AuditLog $log): bool => $log->user_id === $user->id));

        $this->get($this->url('/'.$student->id))->assertOk()->assertSee('Aluno cadastrado e matriculado nesta escola.');
    }

    public function test_gestor_municipal_cadastra_em_qualquer_escola_do_municipio(): void
    {
        $user = $this->conta('gestor-grava@example.invalid', 'municipal_manager');
        $this->entrarComSegundoFator($user);

        $this->post('/m/municipio-a/escolas/'.self::ESCOLA_A2.'/alunos', $this->dados(['full_name' => 'Caio Fictício Gestor']))->assertRedirect();

        $this->assertTrue($this->noMunicipio($this->municipioA, fn (): bool => StudentEnrollment::query()->where('school_id', self::ESCOLA_A2)->exists()));
    }

    public function test_quem_nao_pode_gravar_recebe_403_sem_gravacao_parcial(): void
    {
        $professor = $this->conta('professor@example.invalid', 'teacher', [self::ESCOLA_A1]);
        $outraEscola = $this->conta('outra-escola@example.invalid', 'school_secretary', [self::ESCOLA_A2]);
        $semSegundoFator = $this->conta('sem-2fa@example.invalid', 'school_secretary', [self::ESCOLA_A1], segundoFator: false);
        $outroMunicipio = $this->criarUsuario('outro-municipio@example.invalid');
        $this->vincular($outroMunicipio, $this->municipioB, 'municipal_manager');

        $this->entrarComSegundoFator($professor);
        $this->post($this->url(), $this->dados())->assertForbidden();
        $this->get($this->url())->assertOk()->assertDontSee('Novo aluno')->assertSee('Somente consulta');

        $this->entrarComSegundoFator($outraEscola);
        $this->post($this->url(), $this->dados())->assertForbidden();

        $this->actingAs($semSegundoFator)->post($this->url(), $this->dados())->assertForbidden();
        $this->get($this->url('/novo'))->assertRedirect('/seguranca/segundo-fator/desafio');

        $this->actingAs($outroMunicipio)->post($this->url(), $this->dados())->assertForbidden();

        $this->assertSame(0, $this->noMunicipio($this->municipioA, fn (): int => Student::query()->count()));
    }

    public function test_cadastro_manual_exibe_ajuda_e_erro_claro_para_ra_e_aceita_formato_sed(): void
    {
        $user = $this->conta('ra-cadastro@example.invalid', 'school_secretary', [self::ESCOLA_A1]);
        $this->entrarComSegundoFator($user);

        $this->get($this->url('/novo'))
            ->assertOk()
            ->assertSee('Formato da SED: números, hífen, dígito e UF. Ex.: 000123456789-0/SP. Pode ficar em branco.')
            ->assertSee('aria-describedby="ra-ajuda"', false);

        $css = file_get_contents(public_path('css/astuti.css'));
        $this->assertNotFalse($css);
        $this->assertMatchesRegularExpression(
            '/\\.texto-ajuda\\s*\\{\\s*margin:\\s*4px 0 0;\\s*color:\\s*var\\(--tinta-3\\);\\s*font-size:\\s*13px\\s*\\}/',
            $css,
        );

        $this->from($this->url('/novo'))->post($this->url(), $this->dados(['ra' => '000123456789']))
            ->assertRedirect($this->url('/novo'))
            ->assertSessionHasErrors(['ra' => 'Informe o RA no formato da SED, por exemplo 000123456789-0/SP.']);

        $this->assertSame(0, $this->noMunicipio($this->municipioA, fn (): int => Student::query()->count()));

        $this->post($this->url(), $this->dados(['ra' => str_repeat('1', 33)]))
            ->assertSessionHasErrors('ra');
        $this->assertStringContainsString('RA', session('errors')->first('ra'));

        $this->post($this->url(), $this->dados(['ra' => '000123456789-0/SP']))
            ->assertRedirect();

        $raGravado = $this->noMunicipio($this->municipioA, fn (): ?string => Student::query()->firstOrFail()->ra);
        $this->assertSame('000123456789-0/SP', $raGravado);
    }

    public function test_edicao_manual_mostra_erro_de_ra_e_aceita_formato_sed(): void
    {
        $user = $this->conta('ra-edicao@example.invalid', 'school_director', [self::ESCOLA_A1]);
        $this->entrarComSegundoFator($user);

        $this->post($this->url(), $this->dados())->assertRedirect();
        [$id, $versao] = $this->noMunicipio($this->municipioA, function (): array {
            $student = Student::query()->firstOrFail();
            $row = DB::connection('tenant')->table('students')->where('id', $student->id)
                ->selectRaw('xmin::text AS versao')->first();

            return [$student->id, $row->versao];
        });

        $this->get($this->url('/'.$id.'/editar'))
            ->assertOk()
            ->assertSee('aria-describedby="ra-ajuda"', false);

        $this->from($this->url('/'.$id.'/editar'))->put($this->url('/'.$id), [
            'full_name' => 'Lívia Fictícia de Souza',
            'birth_date' => '2019-05-10',
            'ra' => '000987654321',
            'versao' => $versao,
        ])->assertSessionHasErrors(['ra' => 'Informe o RA no formato da SED, por exemplo 000123456789-0/SP.']);

        $this->assertNull($this->noMunicipio($this->municipioA, fn (): ?string => Student::query()->findOrFail($id)->ra));

        $this->put($this->url('/'.$id), [
            'full_name' => 'Lívia Fictícia de Souza',
            'birth_date' => '2019-05-10',
            'ra' => '000987654321-1/SP',
            'versao' => $versao,
        ])->assertRedirect($this->url('/'.$id));

        $this->assertSame('000987654321-1/SP', $this->noMunicipio($this->municipioA, fn (): ?string => Student::query()->findOrFail($id)->ra));
    }

    public function test_validacao_em_portugues_e_ano_encerrado_recusado(): void
    {
        $user = $this->conta('valida@example.invalid', 'school_secretary', [self::ESCOLA_A1]);
        $this->entrarComSegundoFator($user);

        $this->from($this->url('/novo'))->post($this->url(), $this->dados(['full_name' => 'Ana', 'birth_date' => '2999-01-01']))
            ->assertRedirect($this->url('/novo'))
            ->assertSessionHasErrors(['full_name', 'birth_date']);

        $this->post($this->url(), $this->dados(['full_name' => 'Nome com n4mero']))->assertSessionHasErrors('full_name');
        $this->post($this->url(), $this->dados(['started_at' => '2026-12-01']))->assertSessionHasErrors('started_at');

        $encerrado = $this->noMunicipio($this->municipioA, fn (): string => SchoolYear::query()->create([
            'id' => (string) Str::uuid(), 'year' => 2026, 'start_date' => '2026-02-01', 'end_date' => '2026-12-18', 'status' => 'completed',
        ])->id);
        $this->post($this->url(), $this->dados(['school_year_id' => $encerrado]))->assertSessionHasErrors('school_year_id');

        $erros = session('errors')->getBag('default')->all();
        $this->assertContains('Escolha um ano letivo em andamento ou planejado.', $erros);
        $this->assertSame(0, $this->noMunicipio($this->municipioA, fn (): int => Student::query()->count()));
    }

    public function test_possivel_duplicidade_exige_confirmacao_e_nunca_funde(): void
    {
        $user = $this->conta('duplicado@example.invalid', 'school_secretary', [self::ESCOLA_A1]);
        $this->entrarComSegundoFator($user);

        $this->post($this->url(), $this->dados(['full_name' => 'João Fictício Repetido']))->assertRedirect();

        // Mesmo nome, sem acento e com outra caixa, e mesma data: a tela pede confirmação.
        $this->post($this->url(), $this->dados(['full_name' => 'joao FICTICIO repetido']))
            ->assertSessionHasErrors('full_name')
            ->assertSessionHas('possivel_duplicidade', true);
        $this->assertSame(1, $this->noMunicipio($this->municipioA, fn (): int => Student::query()->count()));

        $this->post($this->url(), $this->dados(['full_name' => 'joao FICTICIO repetido', 'confirmar_duplicidade' => '1']))->assertRedirect();
        $this->assertSame(2, $this->noMunicipio($this->municipioA, fn (): int => Student::query()->count()));
    }

    public function test_edicao_auditada_com_antes_e_depois(): void
    {
        $user = $this->conta('edita@example.invalid', 'school_director', [self::ESCOLA_A1]);
        $this->entrarComSegundoFator($user);
        $this->post($this->url(), $this->dados(['full_name' => 'Nome Fictício Errado']))->assertRedirect();
        [$id, $versao] = $this->noMunicipio($this->municipioA, function (): array {
            $student = Student::query()->firstOrFail();

            return [$student->id, DB::connection('tenant')->table('students')->where('id', $student->id)->selectRaw('xmin::text AS token')->first()->token];
        });

        $this->get($this->url('/'.$id.'/editar'))->assertOk()->assertSee('Nome Fictício Errado');

        $this->put($this->url('/'.$id), [
            'full_name' => 'Nome Fictício Corrigido',
            'birth_date' => '2019-05-11',
            'versao' => $versao,
        ])->assertRedirect($this->url('/'.$id));

        [$before, $after, $actor] = $this->noMunicipio($this->municipioA, function () use ($id): array {
            $log = AuditLog::query()->where('auditable_type', Student::class)->where('auditable_id', $id)
                ->where('action', '!=', 'student.viewed')->orderByDesc('occurred_at')->firstOrFail();

            return [$log->before, $log->after, $log->user_id];
        });
        $this->assertSame('Nome Fictício Errado', $before['full_name']);
        $this->assertSame('Nome Fictício Corrigido', $after['full_name']);
        $this->assertSame($user->id, $actor);
    }

    public function test_edicao_concorrente_e_recusada(): void
    {
        $user = $this->conta('concorrente@example.invalid', 'school_secretary', [self::ESCOLA_A1]);
        $this->entrarComSegundoFator($user);
        $this->post($this->url(), $this->dados(['full_name' => 'Aluna Fictícia Original']))->assertRedirect();
        [$id, $versaoAntiga] = $this->noMunicipio($this->municipioA, function (): array {
            $student = Student::query()->firstOrFail();

            return [$student->id, DB::connection('tenant')->table('students')->where('id', $student->id)->selectRaw('xmin::text AS token')->first()->token];
        });

        $this->travel(2)->seconds();
        $this->put($this->url('/'.$id), ['full_name' => 'Alteração Fictícia Primeira', 'versao' => $versaoAntiga])->assertRedirect();

        $this->put($this->url('/'.$id), ['full_name' => 'Alteração Fictícia Atrasada', 'versao' => $versaoAntiga])
            ->assertSessionHasErrors('versao');

        $this->assertSame('Alteração Fictícia Primeira', $this->noMunicipio($this->municipioA, fn (): string => Student::query()->firstOrFail()->full_name));
    }

    public function test_aluno_sem_vinculo_ativo_na_escola_nao_pode_ser_editado_por_ela(): void
    {
        $user = $this->conta('vinculo@example.invalid', 'school_secretary', [self::ESCOLA_A1]);
        $this->entrarComSegundoFator($user);

        $alheio = $this->noMunicipio($this->municipioA, function (): string {
            $student = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => 'Aluno Fictício de Outra Escola']);
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $student->id, 'school_id' => self::ESCOLA_A2,
                'school_year_id' => $this->yearId, 'status' => 'active',
            ]);

            return $student->id;
        });

        $this->get($this->url('/'.$alheio.'/editar'))->assertNotFound();
        $this->put($this->url('/'.$alheio), ['full_name' => 'Tentativa Fictícia', 'versao' => 'x'])->assertNotFound();
    }
}
