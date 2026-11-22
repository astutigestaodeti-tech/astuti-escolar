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
use Illuminate\Database\QueryException;
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
            ->assertSessionHasErrors(['ra' => 'RA fora do padrão da SED: falta o dígito verificador depois do hífen e falta a barra com a sigla do estado (/SP). Exemplo: 000123456789-0/SP.']);

        // A mensagem diz o que está errado, em vez de só "formato inválido".
        $this->from($this->url('/novo'))->post($this->url(), $this->dados(['ra' => '123']))
            ->assertSessionHasErrors(['ra' => 'RA fora do padrão da SED: tem só 3 números antes do hífen (são de 9 a 12), falta o dígito verificador depois do hífen e falta a barra com a sigla do estado (/SP). Exemplo: 000123456789-0/SP.']);
        $this->followingRedirects()->from($this->url('/novo'))->post($this->url(), $this->dados(['ra' => '123']))
            ->assertSee('RA fora do padrão da SED')
            ->assertDontSee('campo ra');

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
        ])->assertSessionHasErrors(['ra' => 'RA fora do padrão da SED: falta o dígito verificador depois do hífen e falta a barra com a sigla do estado (/SP). Exemplo: 000123456789-0/SP.']);

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

    public function test_cadastro_e_edicao_de_endereco_com_cep_formatado_ficha_e_auditoria(): void
    {
        $user = $this->conta('endereco-ficticio@example.invalid', 'school_secretary', [self::ESCOLA_A1]);
        $this->entrarComSegundoFator($user);

        $this->get($this->url('/novo'))
            ->assertOk()
            ->assertSee('Endereço')
            ->assertSee('name="address_street"', false)
            ->assertSee('name="postal_code"', false)
            ->assertSee('value="urban"', false)
            ->assertSee('value="rural"', false);

        $this->post($this->url(), $this->dados([
            'address_street' => ' Rua das Acácias ',
            'address_number' => ' 12B ',
            'address_district' => ' Jardim Fictício ',
            'postal_code' => '01234567',
            'residence_zone' => 'urban',
        ]))->assertRedirect();

        [$id, $versao, $created] = $this->noMunicipio($this->municipioA, function (): array {
            $student = Student::query()->firstOrFail();
            $audit = AuditLog::query()->where('auditable_type', Student::class)->where('action', 'created')->firstOrFail();

            return [
                $student->id,
                DB::connection('tenant')->table('students')->where('id', $student->id)->selectRaw('xmin::text AS token')->first()->token,
                $audit->after,
            ];
        });

        $this->assertSame('Rua das Acácias', $created['address_street']);
        $this->assertSame('12B', $created['address_number']);
        $this->assertSame('Jardim Fictício', $created['address_district']);
        $this->assertSame('01234-567', $created['postal_code']);
        $this->assertSame('urban', $created['residence_zone']);

        $this->get($this->url('/'.$id))
            ->assertOk()
            ->assertSee('Rua das Acácias')
            ->assertSee('01234-567')
            ->assertSee('Urbana');

        $this->get($this->url('/'.$id.'/editar'))
            ->assertOk()
            ->assertSee('value="01234-567"', false);

        $this->put($this->url('/'.$id), [
            'full_name' => 'Lívia Fictícia de Souza',
            'birth_date' => '2019-05-10',
            'versao' => $versao,
            'address_street' => 'Avenida dos Ipês',
            'address_number' => 'S/N',
            'address_district' => 'Bairro Modelo',
            'postal_code' => '98765-432',
            'residence_zone' => 'rural',
        ])->assertRedirect($this->url('/'.$id));

        [$student, $updated] = $this->noMunicipio($this->municipioA, function () use ($id): array {
            return [
                Student::query()->findOrFail($id),
                AuditLog::query()->where('auditable_type', Student::class)->where('auditable_id', $id)
                    ->where('action', 'updated')->firstOrFail(),
            ];
        });

        $this->assertSame('Avenida dos Ipês', $student->address_street);
        $this->assertSame('S/N', $student->address_number);
        $this->assertSame('Bairro Modelo', $student->address_district);
        $this->assertSame('98765-432', $student->postal_code);
        $this->assertSame('rural', $student->residence_zone);
        $this->assertSame('Rua das Acácias', $updated->before['address_street']);
        $this->assertSame('Avenida dos Ipês', $updated->after['address_street']);
        $this->assertSame('01234-567', $updated->before['postal_code']);
        $this->assertSame('98765-432', $updated->after['postal_code']);
        $this->assertSame('urban', $updated->before['residence_zone']);
        $this->assertSame('rural', $updated->after['residence_zone']);

        $this->get($this->url('/'.$id))->assertOk()->assertSee('Rural')->assertSee('98765-432');

        // PUT antigo que não envia endereço não apaga os dados já gravados.
        $versaoNova = $this->noMunicipio($this->municipioA, fn (): string => DB::connection('tenant')
            ->table('students')->where('id', $id)->selectRaw('xmin::text AS token')->first()->token);
        $this->put($this->url('/'.$id), [
            'full_name' => 'Lívia Fictícia Revisada',
            'birth_date' => '2019-05-10',
            'versao' => $versaoNova,
        ])->assertRedirect($this->url('/'.$id));
        $this->assertSame('98765-432', $this->noMunicipio($this->municipioA, fn (): ?string => Student::query()->findOrFail($id)->postal_code));

        $versaoLimpeza = $this->noMunicipio($this->municipioA, fn (): string => DB::connection('tenant')
            ->table('students')->where('id', $id)->selectRaw('xmin::text AS token')->first()->token);
        $this->put($this->url('/'.$id), [
            'full_name' => 'Lívia Fictícia Revisada',
            'birth_date' => '2019-05-10',
            'versao' => $versaoLimpeza,
            'address_number' => '',
        ])->assertRedirect($this->url('/'.$id));
        [$number, $street] = $this->noMunicipio($this->municipioA, fn (): array => [
            Student::query()->findOrFail($id)->address_number,
            Student::query()->findOrFail($id)->address_street,
        ]);
        $this->assertNull($number);
        $this->assertSame('Avenida dos Ipês', $street);
    }

    public function test_cep_invalido_e_zona_desconhecida_sao_recusados_em_portugues(): void
    {
        $user = $this->conta('endereco-invalido@example.invalid', 'school_secretary', [self::ESCOLA_A1]);
        $this->entrarComSegundoFator($user);

        $this->post($this->url(), $this->dados([
            'postal_code' => '12A45678',
            'residence_zone' => 'litoral',
        ]))->assertSessionHasErrors([
            'postal_code' => 'Informe o CEP no formato 00000-000.',
            'residence_zone' => 'Selecione a zona de residência: Urbana ou Rural.',
        ]);

        $this->post($this->url(), $this->dados(['postal_code' => '1234-567']))
            ->assertSessionHasErrors(['postal_code' => 'Informe o CEP no formato 00000-000.']);

        $this->assertSame(0, $this->noMunicipio($this->municipioA, fn (): int => Student::query()->count()));
    }

    public function test_check_municipal_impede_zona_fora_de_urban_ou_rural(): void
    {
        $user = $this->conta('endereco-check@example.invalid', 'school_secretary', [self::ESCOLA_A1]);
        $this->entrarComSegundoFator($user);
        $this->post($this->url(), $this->dados())->assertRedirect();

        try {
            $this->noMunicipio($this->municipioA, fn () => DB::connection('tenant')->table('students')
                ->update(['residence_zone' => 'other']));
            $this->fail('A constraint municipal precisa recusar uma zona não permitida.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->getCode());
        }
    }

    public function test_endereco_do_aluno_nao_vaza_para_outra_escola(): void
    {
        $secretaria = $this->conta('endereco-escola-a@example.invalid', 'school_secretary', [self::ESCOLA_A1]);
        $this->entrarComSegundoFator($secretaria);
        $this->post($this->url(), $this->dados([
            'address_street' => 'Rua Fictícia Protegida',
            'postal_code' => '01001000',
        ]))->assertRedirect();

        $id = $this->noMunicipio($this->municipioA, fn (): string => Student::query()->firstOrFail()->id);

        $outra = $this->conta('endereco-escola-b@example.invalid', 'school_secretary', [self::ESCOLA_A2]);
        $this->entrarComSegundoFator($outra);
        $url = '/m/municipio-a/escolas/'.self::ESCOLA_A2.'/alunos/'.$id;

        $this->get($url)->assertNotFound()->assertDontSee('Rua Fictícia Protegida');
        $this->get($url.'/editar')->assertNotFound();
        $this->put($url, [
            'full_name' => 'Tentativa Fictícia Externa',
            'versao' => 'x',
            'address_street' => 'Endereço Indevido',
        ])->assertNotFound();

        $this->assertSame('Rua Fictícia Protegida', $this->noMunicipio($this->municipioA, fn (): ?string => Student::query()->findOrFail($id)->address_street));
    }
}
