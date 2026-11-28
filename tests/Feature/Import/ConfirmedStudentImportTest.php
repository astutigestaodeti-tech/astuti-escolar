<?php

namespace Tests\Feature\Import;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\Guardian;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentGuardian;
use App\Models\User;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;
use ZipArchive;

class ConfirmedStudentImportTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private const ESCOLA_A3 = '44444444-4444-4444-8444-444444444444';

    private const KIT = __DIR__.'/../../Fixtures/kit-monte-sereno/';

    private string $yearId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->yearId = app(TenantContext::class)->runFor($this->municipioA, function (): string {
            return SchoolYear::query()->create([
                'id' => (string) Str::uuid(),
                'year' => 2027,
                'start_date' => '2027-02-01',
                'end_date' => '2027-12-18',
                'status' => 'active',
            ])->id;
        });
    }

    private function criarTurmas(string $schoolId, array $names): void
    {
        app(TenantContext::class)->runFor($this->municipioA, function () use ($schoolId, $names): void {
            foreach ($names as $name) {
                SchoolClass::query()->create([
                    'id' => (string) Str::uuid(),
                    'school_id' => $schoolId,
                    'school_year_id' => $this->yearId,
                    'name' => $name,
                    'stage' => 'Fictícia',
                    'modality' => 'Regular',
                    'shift' => match ($name) {
                        'MII-A' => 'Integral',
                        'PII-A', '4A', '5A', 'MULTI-2' => 'Tarde',
                        default => 'Manhã',
                    },
                    'status' => 'active',
                ]);
            }
        });
    }

    private function entrar(string $role = 'school_secretary', array $schools = [self::ESCOLA_A1]): User
    {
        $user = $this->criarUsuario(Str::random(12).'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schools);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp()),
        ])->assertRedirect();

        return $user;
    }

    private function url(string $schoolId = self::ESCOLA_A1): string
    {
        return '/m/municipio-a/escolas/'.$schoolId.'/importacoes/alunos';
    }

    /** @return array{string, list<int>, string} */
    private function preparar(string $file, string $schoolId = self::ESCOLA_A1, ?string $path = null): array
    {
        $response = $this->post($this->url($schoolId).'/previa', [
            'arquivo' => new UploadedFile($path ?? self::KIT.$file, $file, null, null, true),
            'school_year_id' => $this->yearId,
        ]);
        $response->assertOk()->assertSee('Conferir antes de importar');
        $html = $response->getContent();

        $this->assertSame(1, preg_match('/name="lote" value="([^"]+)"/', $html, $matches));
        preg_match_all('/name="confirmados\[\]" value="(\d+)"/', $html, $indices);

        return [$matches[1], array_map('intval', $indices[1]), $html];
    }

    /** @return array{int,int,int} */
    private function contagens(): array
    {
        return app(TenantContext::class)->runFor($this->municipioA, fn (): array => [
            Student::query()->count(),
            StudentEnrollment::query()->count(),
            DB::connection('tenant')->table('student_import_receipts')->count(),
        ]);
    }

    public function test_importacao_preserva_nascimento_de_hoje_e_deixa_ausente_ou_futuro_pendente(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['1A']);
        $this->entrar();
        $today = new \DateTimeImmutable('today');
        $file = $this->planilha([
            ['Nome', 'Data de nascimento', 'Turma'],
            ['Alice Recém Nascida Fictícia', $today->format('d/m/Y'), '1A'],
            ['Bruno Sem Data Fictício', '', '1A'],
            ['Clara Data Futura Fictícia', $today->modify('+2 days')->format('d/m/Y'), '1A'],
        ]);
        try {
            [$token, $confirmations] = $this->preparar('nascimentos.xlsx', self::ESCOLA_A1, $file);
        } finally {
            unlink($file);
        }

        $rows = collect(json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR)['rows'])->keyBy('name');
        $this->assertCount(3, $rows);
        $this->assertSame($today->format('Y-m-d'), $rows['Alice Recém Nascida Fictícia']['birth_date']);
        $this->assertNotContains('data_nascimento', array_column($rows['Alice Recém Nascida Fictícia']['pending'], 'field'));
        foreach (['Bruno Sem Data Fictício', 'Clara Data Futura Fictícia'] as $name) {
            $this->assertNull($rows[$name]['birth_date']);
            $this->assertContains('data_nascimento', array_column($rows[$name]['pending'], 'field'));
        }
        $this->assertSame([0, 0, 0], $this->contagens());
        $this->post($this->url().'/confirmar', ['lote' => $token, 'confirmados' => $confirmations])->assertRedirect($this->url());
        $this->assertSame([3, 3, 1], $this->contagens());

        $dates = app(TenantContext::class)->runFor($this->municipioA, fn (): array => Student::query()->get()
            ->mapWithKeys(fn (Student $student): array => [$student->full_name => $student->birth_date?->format('Y-m-d')])->sortKeys()->all());
        $this->assertSame([
            'Alice Recém Nascida Fictícia' => $today->format('Y-m-d'),
            'Bruno Sem Data Fictício' => null,
            'Clara Data Futura Fictícia' => null,
        ], $dates);
        $this->post($this->url().'/confirmar', ['lote' => $token, 'confirmados' => $confirmations])->assertSessionHasErrors('lote');
        $this->assertSame([3, 3, 1], $this->contagens());
    }

    public function test_importacao_preserva_zeros_do_ra_e_cpf_e_deixa_uf_inexistente_pendente(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['1A']);
        $this->entrar();
        $file = $this->planilha([
            ['Nome', 'Data de nascimento', 'Turma', 'RA', 'CPF do aluno'],
            ['Ana Identidade Fictícia', '01/02/2020', '1A', '0001234567890/sp', '01234567890'],
            ['Bia Documento Fictício', '03/04/2020', '1A', '000987654321-1/XX', '00000000000'],
            ['Caio Sem Documento Fictício', '05/06/2020', '1A', '', ''],
        ]);
        try {
            [$token, $confirmations] = $this->preparar('identidade.xlsx', self::ESCOLA_A1, $file);
        } finally {
            unlink($file);
        }
        $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        $rows = collect($payload['rows'])->keyBy('name');
        $this->assertSame('000123456789-0/SP', $rows['Ana Identidade Fictícia']['ra']);
        $this->assertSame('012.345.678-90', $rows['Ana Identidade Fictícia']['census']['cpf']);
        $this->assertNull($rows['Bia Documento Fictício']['ra']);
        $this->assertArrayNotHasKey('cpf', $rows['Bia Documento Fictício']['census']);
        $this->assertContains('ra', array_column($rows['Bia Documento Fictício']['pending'], 'field'));
        $this->assertContains('cpf', array_column($rows['Bia Documento Fictício']['pending'], 'field'));
        $this->assertNull($rows['Caio Sem Documento Fictício']['ra']);
        $this->assertArrayNotHasKey('cpf', $rows['Caio Sem Documento Fictício']['census']);

        // Prévia antiga autenticada com UF inválida também não pode gravar.
        $payload['rows'][0]['ra'] = '000123456789-0/XX';
        $oldToken = Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $this->post($this->url().'/confirmar', ['lote' => $oldToken, 'confirmados' => $confirmations])->assertSessionHasErrors('lote');
        $this->assertSame([0, 0, 0], $this->contagens());

        $this->post($this->url().'/confirmar', ['lote' => $token, 'confirmados' => $confirmations])->assertRedirect($this->url());
        $this->assertSame([3, 3, 1], $this->contagens());
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $students = Student::query()->get()->keyBy('full_name');
            $this->assertSame('000123456789-0/SP', $students['Ana Identidade Fictícia']->ra);
            $this->assertSame('012.345.678-90', $students['Ana Identidade Fictícia']->cpf);
            foreach (['Bia Documento Fictício', 'Caio Sem Documento Fictício'] as $name) {
                $this->assertNull($students[$name]->ra);
                $this->assertNull($students[$name]->cpf);
            }
        });
    }

    public function test_envio_e_previa_nao_gravam_alunos_nem_matriculas(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['1A', '2A', '3A', '4A', '5A']);
        $this->entrar();

        [$token, $confirmations] = $this->preparar('E02_EMEF_Sebastiao_alunos_2027.xlsx');

        $this->assertNotEmpty($token);
        $this->assertCount(2, $confirmations);
        $this->assertSame([0, 0, 0], $this->contagens());
    }

    public function test_confirmacao_explicita_grava_58_matriculas_auditadas_uma_so_vez(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['1A', '2A', '3A', '4A', '5A']);
        $user = $this->entrar();
        [$token, $indices] = $this->preparar('E02_EMEF_Sebastiao_alunos_2027.xlsx');

        $this->post($this->url().'/confirmar', ['lote' => $token])
            ->assertSessionHasErrors('confirmados');
        $this->assertSame([0, 0, 0], $this->contagens());

        $this->post($this->url().'/confirmar', ['lote' => $token, 'confirmados' => $indices])
            ->assertRedirect($this->url())
            ->assertSessionHas('status');

        $this->assertSame([58, 58, 1], $this->contagens());
        $audit = app(TenantContext::class)->runFor($this->municipioA, fn (): array => [
            AuditLog::query()->where('auditable_type', Student::class)->where('action', 'created')->count(),
            AuditLog::query()->where('auditable_type', StudentEnrollment::class)->where('action', 'created')->count(),
            AuditLog::query()->where('action', 'student_import.completed')->where('user_id', $user->id)->count(),
        ]);
        $this->assertSame([58, 58, 1], $audit);

        $this->post($this->url().'/confirmar', ['lote' => $token, 'confirmados' => $indices])
            ->assertSessionHasErrors('lote');
        $this->assertSame([58, 58, 1], $this->contagens());
    }

    public function test_cadastro_criado_apos_previa_exige_nova_conferencia_sem_gravacao_parcial(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['1A', '2A', '3A', '4A', '5A']);
        $this->entrar();
        [$token, $indices] = $this->preparar('E02_EMEF_Sebastiao_alunos_2027.xlsx');

        app(TenantContext::class)->runFor($this->municipioA, fn () => Student::query()->create([
            'id' => (string) Str::uuid(),
            'full_name' => 'Laura Souza Gonçalves',
            'birth_date' => '2020-12-10',
        ]));

        $this->post($this->url().'/confirmar', ['lote' => $token, 'confirmados' => $indices])
            ->assertSessionHasErrors('lote');
        $this->assertSame([1, 0, 0], $this->contagens());
    }

    public function test_sem_permissao_ou_sem_segundo_fator_nao_importa(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['1A', '2A', '3A', '4A', '5A']);
        $this->entrar('teacher');
        $this->get($this->url())->assertForbidden();
        $this->post($this->url().'/previa', [])->assertForbidden();

        $this->entrar('school_secretary', [self::ESCOLA_A2]);
        $this->get($this->url())->assertForbidden();

        $user = $this->criarUsuario('sem-fator-import@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);
        $this->actingAs($user)->get($this->url())->assertRedirect('/seguranca/segundo-fator/desafio');
        $this->actingAs($user)->post($this->url().'/confirmar', ['lote' => 'x'])->assertForbidden();
        $this->assertSame([0, 0, 0], $this->contagens());
    }

    public function test_token_adulterado_ou_enviado_por_outra_conta_e_recusado(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['1A', '2A', '3A', '4A', '5A']);
        $this->entrar();
        [$token, $indices] = $this->preparar('E02_EMEF_Sebastiao_alunos_2027.xlsx');

        $this->post($this->url().'/confirmar', ['lote' => $token.'x', 'confirmados' => $indices])
            ->assertSessionHasErrors('lote');

        $this->entrar();
        $this->post($this->url().'/confirmar', ['lote' => $token, 'confirmados' => $indices])
            ->assertSessionHasErrors('lote');
        $this->assertSame([0, 0, 0], $this->contagens());
    }

    public function test_gabarito_das_tres_escolas_grava_115_alunos_sem_inventar_datas(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['MII-A', 'PI-A', 'PII-A']);
        $this->criarTurmas(self::ESCOLA_A2, ['1A', '2A', '3A', '4A', '5A']);
        app(TenantContext::class)->runFor($this->municipioA, fn () => School::query()->create([
            'id' => self::ESCOLA_A3, 'name' => 'Escola Rural Fictícia', 'status' => 'active',
        ]));
        $this->criarTurmas(self::ESCOLA_A3, ['MULTI-1', 'MULTI-2']);
        $this->entrar('municipal_manager', []);

        foreach ([
            [self::ESCOLA_A1, 'E01_EMEI_Aurora_lista_criancas_2027.xlsx'],
            [self::ESCOLA_A2, 'E02_EMEF_Sebastiao_alunos_2027.xlsx'],
            [self::ESCOLA_A3, 'E03_Rural_Palmeiras.xlsx'],
        ] as [$schoolId, $file]) {
            [$token, $indices] = $this->preparar($file, $schoolId);
            $this->post($this->url($schoolId).'/confirmar', ['lote' => $token, 'confirmados' => $indices])
                ->assertRedirect($this->url($schoolId));
        }

        $this->assertSame([115, 115, 3], $this->contagens());
        $missingBirthDates = app(TenantContext::class)->runFor($this->municipioA, fn (): int => Student::query()->whereNull('birth_date')->count());
        $this->assertSame(6, $missingBirthDates);
        $this->assertSame(115, app(TenantContext::class)->runFor($this->municipioA, fn (): int => StudentEnrollment::query()->whereNotNull('school_class_id')->count()));

        $expectedSchool = ['E01' => self::ESCOLA_A1, 'E02' => self::ESCOLA_A2, 'E03' => self::ESCOLA_A3];
        $handle = fopen(self::KIT.'alunos.csv', 'r');
        $headers = fgetcsv($handle, escape: '');
        while (($line = fgetcsv($handle, escape: '')) !== false) {
            $expected = array_combine($headers, $line);
            [$student, $enrollment] = app(TenantContext::class)->runFor($this->municipioA, function () use ($expected): array {
                $student = Student::query()->where('full_name', $expected['nome'])->firstOrFail();

                return [$student, StudentEnrollment::query()->with('schoolClass')->where('student_id', $student->id)->firstOrFail()];
            });
            $this->assertSame($expectedSchool[$expected['escola_id']], $enrollment->school_id);
            $this->assertSame(substr($expected['turma_id'], 4), $enrollment->schoolClass->name);
            if ($student->birth_date !== null) {
                $this->assertSame($expected['data_nascimento'], $student->birth_date->format('Y-m-d'));
            }
        }
        fclose($handle);
    }

    public function test_reimportar_e02_ignora_58_e_registra_o_recibo_sem_erro(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['1A', '2A', '3A', '4A', '5A']);
        $this->entrar();

        [$first, $confirmations] = $this->preparar('E02_EMEF_Sebastiao_alunos_2027.xlsx');
        $this->post($this->url().'/confirmar', [
            'lote' => $first,
            'confirmados' => $confirmations,
        ])->assertRedirect($this->url());
        $this->assertSame([58, 58, 1], $this->contagens());

        [$again, $confirmations] = $this->preparar('E02_EMEF_Sebastiao_alunos_2027.xlsx');
        $this->assertSame([], $confirmations);
        $rows = json_decode(Crypt::decryptString($again), true, 512, JSON_THROW_ON_ERROR)['rows'];
        $this->assertCount(58, array_filter($rows, fn (array $row): bool => in_array(
            'ja_cadastrado', array_column($row['confirmation'], 'field'), true,
        )));

        $this->post($this->url().'/confirmar', ['lote' => $again])
            ->assertRedirect($this->url());
        $this->assertSame([58, 58, 2], $this->contagens());

        $receipt = app(TenantContext::class)->runFor($this->municipioA, fn () => DB::connection('tenant')
            ->table('student_import_receipts')->where('student_count', 0)->first());
        $this->assertNotNull($receipt);
        $this->assertSame(58, (int) $receipt->ignored_count);
    }

    public function test_reimportar_lista_com_um_aluno_novo_grava_so_ele(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['1A', '2A', '3A', '4A', '5A']);
        $this->entrar();

        [$initial, $confirmations] = $this->preparar('E02_EMEF_Sebastiao_alunos_2027.xlsx');
        $rows = json_decode(Crypt::decryptString($initial), true, 512, JSON_THROW_ON_ERROR)['rows'];
        $this->post($this->url().'/confirmar', [
            'lote' => $initial,
            'confirmados' => $confirmations,
        ])->assertRedirect($this->url());

        $file = $this->planilhaComAlunoNovo($rows);
        try {
            [$updated, $confirmations] = $this->preparar('E02_atualizada.xlsx', self::ESCOLA_A1, $file);
        } finally {
            unlink($file);
        }

        $updatedRows = json_decode(Crypt::decryptString($updated), true, 512, JSON_THROW_ON_ERROR)['rows'];
        $this->assertCount(59, $updatedRows);
        $this->assertCount(58, array_filter($updatedRows, fn (array $row): bool => in_array(
            'ja_cadastrado', array_column($row['confirmation'], 'field'), true,
        )));
        $this->assertSame([], $confirmations);

        $this->post($this->url().'/confirmar', ['lote' => $updated])
            ->assertRedirect($this->url());
        $this->assertSame([59, 59, 2], $this->contagens());

        $new = app(TenantContext::class)->runFor($this->municipioA, fn () => Student::query()
            ->where('full_name', 'Nova Aluna Fictícia de Teste')->firstOrFail());
        $this->assertSame('999999999999-1/SP', $new->ra);

        $receipt = app(TenantContext::class)->runFor($this->municipioA, fn () => DB::connection('tenant')
            ->table('student_import_receipts')->where('student_count', 1)->first());
        $this->assertNotNull($receipt);
        $this->assertSame(58, (int) $receipt->ignored_count);
    }

    public function test_homonimo_sem_nascimento_so_grava_com_marcacao_explicita(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, fn () => School::query()->create([
            'id' => self::ESCOLA_A3, 'name' => 'Escola Rural Fictícia', 'status' => 'active',
        ]));
        $this->criarTurmas(self::ESCOLA_A3, ['MULTI-1', 'MULTI-2']);
        $this->entrar('municipal_manager', []);

        [$first] = $this->preparar('E03_Rural_Palmeiras.xlsx', self::ESCOLA_A3);
        $rows = json_decode(Crypt::decryptString($first), true, 512, JSON_THROW_ON_ERROR)['rows'];
        $index = null;
        foreach ($rows as $position => $row) {
            if ($row['birth_date'] === null) {
                $index = $position;
                break;
            }
        }
        $this->assertNotNull($index);

        app(TenantContext::class)->runFor($this->municipioA, fn () => Student::query()->create([
            'id' => (string) Str::uuid(),
            'full_name' => $rows[$index]['name'],
            'birth_date' => null,
            'ra' => null,
        ]));

        [$lote, $confirmations] = $this->preparar('E03_Rural_Palmeiras.xlsx', self::ESCOLA_A3);
        $reviewed = json_decode(Crypt::decryptString($lote), true, 512, JSON_THROW_ON_ERROR)['rows'];
        $this->assertContains('homonimo', array_column($reviewed[$index]['confirmation'], 'field'));

        $this->post($this->url(self::ESCOLA_A3).'/confirmar', [
            'lote' => $lote,
            'confirmados' => $confirmations,
        ])->assertSessionHasErrors('homonimos');
        $this->assertSame([1, 0, 0], $this->contagens());

        $this->post($this->url(self::ESCOLA_A3).'/confirmar', [
            'lote' => $lote,
            'confirmados' => $confirmations,
            'homonimos' => [$index],
        ])->assertRedirect($this->url(self::ESCOLA_A3));

        $this->assertSame([18, 17, 1], $this->contagens());
    }

    public function test_planilha_e02_grava_endereco_da_fonte_em_alunos_novos_sem_inventar_cep(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['1A', '2A', '3A', '4A', '5A']);
        $this->entrar();

        [$token, $indices] = $this->preparar('E02_EMEF_Sebastiao_alunos_2027.xlsx');
        $rows = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR)['rows'];
        $first = $rows[0];
        $this->assertNotEmpty($first['address']['address_street']);
        $this->assertNotEmpty($first['address']['address_number']);
        $this->assertNotEmpty($first['address']['address_district']);
        $this->assertNull($first['address']['postal_code']);

        $this->post($this->url().'/confirmar', ['lote' => $token, 'confirmados' => $indices])
            ->assertRedirect($this->url());

        [$student, $audit] = app(TenantContext::class)->runFor($this->municipioA, function () use ($first): array {
            $student = Student::query()->where('full_name', $first['name'])->firstOrFail();
            $audit = AuditLog::query()->where('auditable_type', Student::class)
                ->where('auditable_id', $student->id)->where('action', 'created')->firstOrFail();

            return [$student, $audit];
        });

        $this->assertSame($first['address']['address_street'], $student->address_street);
        $this->assertSame($first['address']['address_number'], $student->address_number);
        $this->assertSame($first['address']['address_district'], $student->address_district);
        $this->assertNull($student->postal_code);
        $this->assertSame($student->address_street, $audit->after['address_street']);
    }

    public function test_reimportacao_so_completa_endereco_vazio_apos_marcacao_individual_e_audita(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['1A', '2A', '3A', '4A', '5A']);
        $this->entrar();

        [$first, $indices] = $this->preparar('E02_EMEF_Sebastiao_alunos_2027.xlsx');
        $rows = json_decode(Crypt::decryptString($first), true, 512, JSON_THROW_ON_ERROR)['rows'];
        $this->post($this->url().'/confirmar', ['lote' => $first, 'confirmados' => $indices])
            ->assertRedirect($this->url());

        $studentId = app(TenantContext::class)->runFor($this->municipioA, function () use ($rows): string {
            $student = Student::query()->where('full_name', $rows[0]['name'])->firstOrFail();
            $student->address_street = 'Rua Manual Conferida';
            $student->address_number = null;
            $student->address_district = null;
            $student->save();

            return $student->id;
        });

        [$withoutSelection] = $this->preparar('E02_EMEF_Sebastiao_alunos_2027.xlsx');
        $preview = json_decode(Crypt::decryptString($withoutSelection), true, 512, JSON_THROW_ON_ERROR)['rows'];
        $this->assertSame($studentId, $preview[0]['address_target_id']);
        $this->post($this->url().'/confirmar', ['lote' => $withoutSelection])
            ->assertRedirect($this->url());

        $before = app(TenantContext::class)->runFor($this->municipioA, fn () => Student::query()->findOrFail($studentId));
        $this->assertSame('Rua Manual Conferida', $before->address_street);
        $this->assertNull($before->address_number);

        [$withSelection, , $html] = $this->preparar('E02_EMEF_Sebastiao_alunos_2027.xlsx');
        $this->assertStringContainsString('name="completar_enderecos[]"', $html);
        $this->post($this->url().'/confirmar', ['lote' => $withSelection, 'completar_enderecos' => [0]])
            ->assertRedirect($this->url())->assertSessionHas('status', fn (string $message): bool => str_contains($message, '1 cadastro completado'));

        [$after, $audits] = app(TenantContext::class)->runFor($this->municipioA, fn (): array => [
            Student::query()->findOrFail($studentId),
            AuditLog::query()->where('auditable_type', Student::class)->where('auditable_id', $studentId)
                ->where('action', 'updated')->get(),
        ]);
        $this->assertSame('Rua Manual Conferida', $after->address_street);
        $this->assertSame($rows[0]['address']['address_number'], $after->address_number);
        $this->assertSame($rows[0]['address']['address_district'], $after->address_district);
        $this->assertTrue($audits->contains(fn ($audit): bool => ($audit->before['address_number'] ?? null) === null
            && ($audit->after['address_number'] ?? null) === $after->address_number
            && ($audit->after['address_street'] ?? null) === null));
        $this->assertSame([58, 58, 3], $this->contagens());
    }

    public function test_aluno_de_outra_escola_nao_recebe_endereco_pela_lista_importada(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['1A', '2A', '3A', '4A', '5A']);
        $this->entrar();
        [$probe] = $this->preparar('E02_EMEF_Sebastiao_alunos_2027.xlsx');
        $row = json_decode(Crypt::decryptString($probe), true, 512, JSON_THROW_ON_ERROR)['rows'][0];

        $studentId = app(TenantContext::class)->runFor($this->municipioA, function () use ($row): string {
            $student = Student::query()->create([
                'id' => (string) Str::uuid(),
                'full_name' => $row['name'],
                'birth_date' => $row['birth_date'],
                'ra' => $row['ra'],
            ]);
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $student->id,
                'school_id' => self::ESCOLA_A2,
                'school_year_id' => $this->yearId,
                'school_class_id' => null,
                'status' => 'active',
            ]);

            return $student->id;
        });

        [$token, $indices] = $this->preparar('E02_EMEF_Sebastiao_alunos_2027.xlsx');
        $rows = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR)['rows'];
        $this->assertContains('ja_cadastrado', array_column($rows[0]['confirmation'], 'field'));
        $this->assertNull($rows[0]['address_target_id']);

        $this->post($this->url().'/confirmar', [
            'lote' => $token,
            'confirmados' => $indices,
            'completar_enderecos' => [0],
        ])->assertSessionHasErrors('completar_enderecos');

        $unchanged = app(TenantContext::class)->runFor($this->municipioA, fn () => Student::query()->findOrFail($studentId));
        $this->assertNull($unchanged->address_street);
        $this->assertSame([1, 1, 0], $this->contagens());
    }

    public function test_edicao_manual_apos_previa_nao_e_sobrescrita_pela_confirmacao(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['1A', '2A', '3A', '4A', '5A']);
        $this->entrar();
        [$first, $indices] = $this->preparar('E02_EMEF_Sebastiao_alunos_2027.xlsx');
        $rows = json_decode(Crypt::decryptString($first), true, 512, JSON_THROW_ON_ERROR)['rows'];
        $this->post($this->url().'/confirmar', ['lote' => $first, 'confirmados' => $indices])
            ->assertRedirect($this->url());

        $studentId = app(TenantContext::class)->runFor($this->municipioA, function () use ($rows): string {
            $student = Student::query()->where('full_name', $rows[0]['name'])->firstOrFail();
            $student->address_number = null;
            $student->address_district = null;
            $student->save();

            return $student->id;
        });
        [$token] = $this->preparar('E02_EMEF_Sebastiao_alunos_2027.xlsx');

        app(TenantContext::class)->runFor($this->municipioA, function () use ($studentId): void {
            $student = Student::query()->findOrFail($studentId);
            $student->address_number = '777';
            $student->save();
        });

        $this->post($this->url().'/confirmar', ['lote' => $token, 'completar_enderecos' => [0]])
            ->assertRedirect($this->url());

        $student = app(TenantContext::class)->runFor($this->municipioA, fn () => Student::query()->findOrFail($studentId));
        $this->assertSame('777', $student->address_number);
        $this->assertSame($rows[0]['address']['address_district'], $student->address_district);
    }

    public function test_conflito_apos_previa_nao_completa_endereco_nem_grava_novos_alunos(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['1A', '2A', '3A', '4A', '5A']);
        $this->entrar();
        [$first, $indices] = $this->preparar('E02_EMEF_Sebastiao_alunos_2027.xlsx');
        $rows = json_decode(Crypt::decryptString($first), true, 512, JSON_THROW_ON_ERROR)['rows'];
        $this->post($this->url().'/confirmar', ['lote' => $first, 'confirmados' => $indices])
            ->assertRedirect($this->url());

        $studentId = app(TenantContext::class)->runFor($this->municipioA, function () use ($rows): string {
            $student = Student::query()->where('full_name', $rows[0]['name'])->firstOrFail();
            $student->address_number = null;
            $student->save();

            return $student->id;
        });

        $file = $this->planilhaComAlunoNovo($rows, true);
        try {
            [$token] = $this->preparar('E02_novo_endereco.xlsx', self::ESCOLA_A1, $file);
        } finally {
            unlink($file);
        }
        $preview = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR)['rows'];
        $this->assertSame($studentId, $preview[0]['address_target_id']);
        $this->assertCount(59, $preview);

        // Outra gravação ocorre após a prévia: o novo RA deixa de estar livre.
        app(TenantContext::class)->runFor($this->municipioA, fn () => Student::query()->create([
            'id' => (string) Str::uuid(),
            'full_name' => 'Nova Aluna Fictícia de Teste',
            'birth_date' => '2021-01-01',
            'ra' => '999999999999-1/SP',
        ]));

        $this->post($this->url().'/confirmar', [
            'lote' => $token,
            'completar_enderecos' => [0],
        ])->assertSessionHasErrors('lote');

        $unchanged = app(TenantContext::class)->runFor($this->municipioA, fn () => Student::query()->findOrFail($studentId));
        $this->assertNull($unchanged->address_number);
        $this->assertSame([59, 58, 1], $this->contagens());
    }

    public function test_matricula_transferida_nao_autoriza_endereco_da_lista_antiga(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['1A', '2A', '3A', '4A', '5A']);
        $this->entrar();
        [$first, $indices] = $this->preparar('E02_EMEF_Sebastiao_alunos_2027.xlsx');
        $rows = json_decode(Crypt::decryptString($first), true, 512, JSON_THROW_ON_ERROR)['rows'];
        $this->post($this->url().'/confirmar', ['lote' => $first, 'confirmados' => $indices])
            ->assertRedirect($this->url());

        $studentId = app(TenantContext::class)->runFor($this->municipioA, function () use ($rows): string {
            $student = Student::query()->where('full_name', $rows[0]['name'])->firstOrFail();
            $student->address_number = null;
            $student->save();
            $enrollment = StudentEnrollment::query()->where('student_id', $student->id)->firstOrFail();
            $enrollment->status = 'transferred';
            $enrollment->ended_at = '2027-06-15';
            $enrollment->save();

            return $student->id;
        });

        [$token] = $this->preparar('E02_EMEF_Sebastiao_alunos_2027.xlsx');
        $preview = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR)['rows'];
        $this->assertNull($preview[0]['address_target_id']);

        $this->post($this->url().'/confirmar', [
            'lote' => $token,
            'completar_enderecos' => [0],
        ])->assertSessionHasErrors('completar_enderecos');

        $student = app(TenantContext::class)->runFor($this->municipioA, fn () => Student::query()->findOrFail($studentId));
        $this->assertNull($student->address_number);
    }

    public function test_encerramento_apos_previa_recusa_completar_endereco(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['1A', '2A', '3A', '4A', '5A']);
        $this->entrar();
        [$first, $indices] = $this->preparar('E02_EMEF_Sebastiao_alunos_2027.xlsx');
        $rows = json_decode(Crypt::decryptString($first), true, 512, JSON_THROW_ON_ERROR)['rows'];
        $this->post($this->url().'/confirmar', ['lote' => $first, 'confirmados' => $indices])
            ->assertRedirect($this->url());

        $studentId = app(TenantContext::class)->runFor($this->municipioA, function () use ($rows): string {
            $student = Student::query()->where('full_name', $rows[0]['name'])->firstOrFail();
            $student->address_number = null;
            $student->save();

            return $student->id;
        });
        [$token] = $this->preparar('E02_EMEF_Sebastiao_alunos_2027.xlsx');
        $preview = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR)['rows'];
        $this->assertSame($studentId, $preview[0]['address_target_id']);

        app(TenantContext::class)->runFor($this->municipioA, function () use ($studentId): void {
            $enrollment = StudentEnrollment::query()->where('student_id', $studentId)->firstOrFail();
            $enrollment->status = 'left_network';
            $enrollment->ended_at = '2027-06-15';
            $enrollment->save();
        });

        $this->post($this->url().'/confirmar', [
            'lote' => $token,
            'completar_enderecos' => [0],
        ])->assertSessionHasErrors('lote');

        $student = app(TenantContext::class)->runFor($this->municipioA, fn () => Student::query()->findOrFail($studentId));
        $this->assertNull($student->address_number);
        $this->assertSame([58, 58, 1], $this->contagens());
    }

    /**
     * Monta um XLSX verdadeiro a partir das linhas da E02 já conferidas,
     * acrescentando uma aluna fictícia para testar a prévia completa.
     */
    /** Lista fictícia no formato da SED, com as colunas do Censo Escolar. */
    private const CABECALHO_CENSO = ['Nome', 'Data de nascimento', 'Turma', 'Sexo', 'Cor/Raça', 'Nacionalidade', 'Naturalidade', 'CPF do aluno', 'Nome social'];

    /** @return list<array<string, mixed>> */
    private function linhasDoLote(string $token): array
    {
        return json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR)['rows'];
    }

    private function linhaDe(array $rows, string $name): array
    {
        foreach ($rows as $row) {
            if ($row['name'] === $name) {
                return $row;
            }
        }
        $this->fail('Aluno '.$name.' não está na prévia.');
    }

    public function test_importacao_grava_dados_do_censo_e_deixa_invalidos_e_repetidos_pendentes(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['1A']);
        $this->entrar();

        $file = $this->planilha([
            self::CABECALHO_CENSO,
            ['ANA CLARA FICTÍCIA', '01/02/2020', '1A', 'F', 'Parda', 'Brasileira', 'RECIFE/PE', '529.982.247-25', ''],
            ['Bruno Teste Fictício', '03/04/2020', '1A', 'Masculino', 'Azul', 'brasileiro', 'Monte Sereno', '111.222.333-44', 'Bruna Teste'],
            ['Carla Repetida Um', '05/06/2020', '1A', 'Feminino', 'Branca', 'Estrangeira', 'Lisboa', '39053344705', ''],
            ['Daniel Repetido Dois', '07/08/2020', '1A', 'M', 'Não declarada', 'Naturalizado', 'Guarulhos - SP', '390.533.447-05', ''],
        ]);
        try {
            [$token, , $html] = $this->preparar('censo.xlsx', self::ESCOLA_A1, $file);
        } finally {
            unlink($file);
        }

        // A tela mostra que o CPF foi conferido, sem exibir o número.
        $this->assertStringNotContainsString('529.982.247-25', $html);
        $this->assertStringContainsString('CPF válido', $html);
        $this->assertStringContainsString('Natural de Recife/PE', $html);

        $rows = $this->linhasDoLote($token);
        $this->assertSame([
            'sex' => 'female', 'race_color' => 'parda', 'nationality' => 'brasileira',
            'birthplace_city' => 'Recife', 'birthplace_state' => 'PE', 'cpf' => '529.982.247-25',
        ], $this->linhaDe($rows, 'Ana Clara Fictícia')['census']);

        $bruno = $this->linhaDe($rows, 'Bruno Teste Fictício');
        $this->assertSame(['social_name' => 'Bruna Teste', 'sex' => 'male', 'nationality' => 'brasileira', 'birthplace_city' => 'Monte Sereno'], $bruno['census']);
        $pendencias = implode(' | ', array_column($bruno['pending'], 'message'));
        $this->assertStringContainsString('Cor/raça "Azul" não corresponde', $pendencias);
        $this->assertStringContainsString('Naturalidade "Monte Sereno" sem cidade e UF', $pendencias);
        $this->assertStringContainsString('CPF do aluno inválido', $pendencias);

        foreach (['Carla Repetida Um', 'Daniel Repetido Dois'] as $name) {
            $row = $this->linhaDe($rows, $name);
            $this->assertArrayNotHasKey('cpf', $row['census']);
            $this->assertContains('O mesmo CPF aparece em mais de um aluno desta planilha; não foi importado. Confira os documentos e informe na ficha.', array_column($row['pending'], 'message'));
        }
        $this->assertSame('naturalizada', $this->linhaDe($rows, 'Daniel Repetido Dois')['census']['nationality']);
        $this->assertSame('SP', $this->linhaDe($rows, 'Daniel Repetido Dois')['census']['birthplace_state']);

        // Pendências do Censo não impedem a gravação.
        $this->post($this->url().'/confirmar', ['lote' => $token])->assertRedirect($this->url());
        $this->assertSame([4, 4, 1], $this->contagens());

        [$ana, $brunoSalvo] = app(TenantContext::class)->runFor($this->municipioA, fn (): array => [
            Student::query()->where('full_name', 'Ana Clara Fictícia')->firstOrFail(),
            Student::query()->where('full_name', 'Bruno Teste Fictício')->firstOrFail(),
        ]);
        $this->assertSame(['female', 'parda', 'brasileira', 'Recife', 'PE', '529.982.247-25'], [$ana->sex, $ana->race_color, $ana->nationality, $ana->birthplace_city, $ana->birthplace_state, $ana->cpf]);
        $this->assertSame(['Bruna Teste', 'male', null, null], [$brunoSalvo->social_name, $brunoSalvo->sex, $brunoSalvo->race_color, $brunoSalvo->cpf]);
    }

    public function test_importacao_le_rg_e_certidao_e_deixa_pendente_o_que_esta_fora_do_padrao(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['1A']);
        $this->entrar();

        $file = $this->planilha([
            ['Nome', 'Data de nascimento', 'Turma', 'RG do aluno', 'Certidão de nascimento'],
            ['Ana Documentada Fictícia', '01/02/2020', '1A', '12.345.678-9', '123456 01 55 2019 1 00012 034 0001234 56'],
            ['Bia Pendente Fictícia', '03/04/2020', '1A', 'sem rg', '12345'],
        ]);
        try {
            [$token] = $this->preparar('documentos.xlsx', self::ESCOLA_A1, $file);
        } finally {
            unlink($file);
        }

        $rows = $this->linhasDoLote($token);
        $this->assertSame(['rg_number' => '123456789', 'birth_certificate' => '12345601552019100012034000123456'], $this->linhaDe($rows, 'Ana Documentada Fictícia')['census']);
        $pendencias = implode(' | ', array_column($this->linhaDe($rows, 'Bia Pendente Fictícia')['pending'], 'message'));
        $this->assertStringContainsString('RG do aluno fora do padrão', $pendencias);
        $this->assertStringContainsString('Certidão de nascimento fora do padrão', $pendencias);

        $this->post($this->url().'/confirmar', ['lote' => $token])->assertRedirect($this->url());
        $this->assertSame('123456789', app(TenantContext::class)->runFor($this->municipioA, fn (): ?string => Student::query()->where('full_name', 'Ana Documentada Fictícia')->value('rg_number')));
    }

    public function test_cpf_de_outro_aluno_nao_e_importado_e_reimportacao_so_completa_censo_vazio_com_marcacao(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['1A']);
        $this->entrar();

        $primeira = $this->planilha([
            self::CABECALHO_CENSO,
            ['Ana Clara Fictícia', '01/02/2020', '1A', 'F', '', '', '', '529.982.247-25', ''],
        ]);
        try {
            [$token] = $this->preparar('censo.xlsx', self::ESCOLA_A1, $primeira);
        } finally {
            unlink($primeira);
        }
        $this->post($this->url().'/confirmar', ['lote' => $token])->assertRedirect($this->url());

        $segunda = $this->planilha([
            self::CABECALHO_CENSO,
            // Já cadastrada: sexo divergente não substitui; cor/raça e naturalidade vazias são completadas.
            ['Ana Clara Fictícia', '01/02/2020', '1A', 'Masculino', 'Parda', 'Brasileira', 'Recife/PE', '529.982.247-25', ''],
            // Aluno novo com o CPF que já é da Ana: entra sem o CPF.
            ['Eva Nova Fictícia', '09/10/2020', '1A', 'F', 'Branca', 'Brasileira', 'Recife/PE', '529.982.247-25', ''],
        ]);
        try {
            [$token, , $html] = $this->preparar('censo.xlsx', self::ESCOLA_A1, $segunda);
        } finally {
            unlink($segunda);
        }

        $rows = $this->linhasDoLote($token);
        $ana = $this->linhaDe($rows, 'Ana Clara Fictícia');
        $eva = $this->linhaDe($rows, 'Eva Nova Fictícia');
        $this->assertNotNull($ana['address_target_id']);
        $this->assertSame('529.982.247-25', $ana['census']['cpf']);
        $this->assertArrayNotHasKey('cpf', $eva['census']);
        $this->assertContains('Este CPF já está no cadastro de outro aluno da rede; não foi importado. Confira o documento.', array_column($eva['pending'], 'message'));
        $this->assertStringContainsString('data de nascimento, endereço, dados do Censo, transporte e responsável', $html);

        $indice = array_search('Ana Clara Fictícia', array_column($rows, 'name'), true);
        $this->post($this->url().'/confirmar', ['lote' => $token, 'completar_enderecos' => [$indice]])
            ->assertRedirect($this->url())
            ->assertSessionHas('status', fn (string $message): bool => str_contains($message, '1 cadastro completado'));

        [$anaSalva, $evaSalva, $auditoria] = app(TenantContext::class)->runFor($this->municipioA, function (): array {
            $ana = Student::query()->where('full_name', 'Ana Clara Fictícia')->firstOrFail();

            return [
                $ana,
                Student::query()->where('full_name', 'Eva Nova Fictícia')->firstOrFail(),
                AuditLog::query()->where('auditable_type', Student::class)->where('auditable_id', $ana->id)->where('action', 'updated')->get()->last(),
            ];
        });
        $this->assertSame(['female', 'parda', 'brasileira', 'Recife', 'PE'], [$anaSalva->sex, $anaSalva->race_color, $anaSalva->nationality, $anaSalva->birthplace_city, $anaSalva->birthplace_state]);
        $this->assertNull($evaSalva->cpf);
        $this->assertSame('branca', $evaSalva->race_color);
        // A auditoria registra que a cor/raça mudou, sem guardar o valor.
        $this->assertNotNull($auditoria);
        $this->assertSame('[REDACTED]', $auditoria->after['race_color'] ?? null);
    }

    private const CABECALHO_RESPONSAVEL = ['Nome', 'Data de nascimento', 'Turma', 'Mãe', 'Telefone', 'CPF Resp.'];

    public function test_importacao_cadastra_responsaveis_sem_duplicar_irmaos_e_deixa_pendentes_os_incompletos(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['1A']);
        $this->entrar();

        $file = $this->planilha([
            self::CABECALHO_RESPONSAVEL,
            ['Ana Irmã Fictícia', '01/02/2020', '1A', 'Aline Bueno Alves', '(11) 94023-8059', ''],
            ['Bruno Irmão Fictício', '01/03/2018', '1A', 'ALINE BUENO ALVES', '11940238059', ''],
            ['Carla Sem Telefone', '05/06/2020', '1A', 'Rita Fictícia Souza', '94023-8059', ''],
            ['Daniel Só Com CPF', '07/08/2020', '1A', 'Sandra Fictícia Lima', '', '529.982.247-25'],
            ['Eva Com Tudo', '09/10/2020', '1A', 'Paula Fictícia Reis', '(11) 3333-4444', '111.444.777-35'],
        ]);
        try {
            [$token, , $html] = $this->preparar('responsaveis.xlsx', self::ESCOLA_A1, $file);
        } finally {
            unlink($file);
        }

        $this->assertStringContainsString('Responsável: Aline Bueno Alves (Mãe) · (11) 94023-8059', $html);
        $this->assertStringNotContainsString('111.444.777-35', $html);
        $rows = $this->linhasDoLote($token);
        $this->assertNull($this->linhaDe($rows, 'Carla Sem Telefone')['guardian']);
        $this->assertContains('Rita Fictícia Souza não foi cadastrado como responsável porque falta telefone com DDD. Cadastre na ficha.', array_column($this->linhaDe($rows, 'Carla Sem Telefone')['pending'], 'message'));
        $this->assertNull($this->linhaDe($rows, 'Daniel Só Com CPF')['guardian']);

        $this->post($this->url().'/confirmar', ['lote' => $token])
            ->assertRedirect($this->url())
            ->assertSessionHas('status', fn (string $message): bool => str_contains($message, '3 responsáveis vinculados'));

        [$guardians, $links, $ana, $bruno, $audit] = app(TenantContext::class)->runFor($this->municipioA, function (): array {
            $ana = Student::query()->where('full_name', 'Ana Irmã Fictícia')->firstOrFail();

            return [
                Guardian::query()->orderBy('full_name')->get(),
                StudentGuardian::query()->count(),
                StudentGuardian::query()->where('student_id', $ana->id)->sole(),
                StudentGuardian::query()->whereHas('student', fn ($query) => $query->where('full_name', 'Bruno Irmão Fictício'))->sole(),
                AuditLog::query()->where('auditable_type', Guardian::class)->where('action', 'created')->get(),
            ];
        });

        // Irmãos com a mesma mãe e o mesmo telefone: um cadastro só.
        $this->assertSame(['Aline Bueno Alves', 'Paula Fictícia Reis'], $guardians->pluck('full_name')->all());
        $this->assertSame(['11940238059', '1133334444'], $guardians->pluck('phone')->all());
        $this->assertSame([null, '111.444.777-35'], $guardians->pluck('cpf')->all());
        $this->assertSame(3, $links);
        $this->assertSame($ana->guardian_id, $bruno->guardian_id);
        $this->assertSame(['mother', true, false], [$ana->relationship, $ana->is_primary_contact, $ana->is_legal_guardian]);
        $this->assertCount(2, $audit);
        $this->assertTrue($audit->every(fn ($log): bool => ($log->after['phone'] ?? null) === '[REDACTED]'));
    }

    public function test_previa_avisa_nome_invalido_do_responsavel_sem_impedir_importacao_do_aluno(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['1A']);
        $this->entrar();

        $file = $this->planilha([
            self::CABECALHO_RESPONSAVEL,
            ['Ana Aluna Fictícia', '01/02/2020', '1A', 'Nome 123', '11955554444', ''],
            ['Bruno Aluno Fictício', '01/03/2019', '1A', '', '', ''],
            ['Carla Aluna Fictícia', '01/04/2018', '1A', 'Maria Pessoa Fictícia', '11955553333', ''],
        ]);
        try {
            [$token, , $html] = $this->preparar('responsaveis.xlsx', self::ESCOLA_A1, $file);
        } finally {
            unlink($file);
        }

        $message = 'Nome do responsável inválido em uma linha da planilha; esse nome foi ignorado. Confira o nome completo na ficha.';
        $rows = $this->linhasDoLote($token);
        $invalid = $this->linhaDe($rows, 'Ana Aluna Fictícia');
        $missing = $this->linhaDe($rows, 'Bruno Aluno Fictício');
        $this->assertNull($invalid['guardian']);
        $this->assertContains($message, array_column($invalid['pending'], 'message'));
        $this->assertNull($missing['guardian']);
        $this->assertNotContains($message, array_column($missing['pending'], 'message'));
        $this->assertStringContainsString($message, $html);
        $this->assertStringContainsString('nomes de responsáveis inválidos (1 aluno)', $html);
        $this->assertStringNotContainsString('Nome 123', $html);
        $dump = getenv('ASTUTI_DUMP_TELAS') ?: null;
        if ($dump !== null) {
            $html = str_replace(rtrim((string) config('app.url'), '/').'/', 'file://'.public_path().'/', $html);
            file_put_contents(rtrim($dump, '/').'/importacao_responsavel_invalido.html', preg_replace('/\.(css|js)\?v=\d+/', '.$1', $html));
        }

        $this->post($this->url().'/confirmar', ['lote' => $token])->assertRedirect($this->url());
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $this->assertSame(3, Student::query()->count());
            $this->assertSame(1, Guardian::query()->count());
            $this->assertSame('Carla Aluna Fictícia', StudentGuardian::query()->with('student')->sole()->student->full_name);
        });
    }

    public function test_linhas_repetidas_com_responsavel_invalido_e_valido_preservam_apenas_o_vinculo_valido(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['1A']);
        $this->entrar();

        $file = $this->planilha([
            self::CABECALHO_RESPONSAVEL,
            ['Ana Aluna Fictícia', '01/02/2020', '1A', 'Nome 123', '11955554444', ''],
            ['Ana Aluna Fictícia', '01/02/2020', '1A', 'Maria Pessoa Fictícia', '11955553333', ''],
        ]);
        try {
            [$token, $confirmations, $html] = $this->preparar('responsaveis.xlsx', self::ESCOLA_A1, $file);
        } finally {
            unlink($file);
        }

        $rows = $this->linhasDoLote($token);
        $this->assertCount(1, $rows);
        $this->assertCount(1, $confirmations);
        $this->assertSame('Maria Pessoa Fictícia', $rows[0]['guardian']['name']);
        $this->assertStringContainsString('Responsável: Maria Pessoa Fictícia', $html);
        $this->assertStringContainsString('Nome do responsável inválido em uma linha da planilha; esse nome foi ignorado.', $html);
        $this->assertStringNotContainsString('Nome 123', $html);

        $this->post($this->url().'/confirmar', ['lote' => $token, 'confirmados' => $confirmations])->assertRedirect($this->url());
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $this->assertSame(1, Student::query()->count());
            $this->assertSame(1, StudentEnrollment::query()->count());
            $this->assertSame(1, Guardian::query()->count());
            $this->assertSame('Maria Pessoa Fictícia', StudentGuardian::query()->with('guardian')->sole()->guardian->full_name);
        });
    }

    public function test_linhas_repetidas_com_responsaveis_divergentes_nao_escolhem_vinculo_arbitrariamente(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['1A']);
        $this->entrar();
        $file = $this->planilha([
            self::CABECALHO_RESPONSAVEL,
            ['Ana Aluna Fictícia', '01/02/2020', '1A', 'Maria Pessoa Fictícia', '11955553333', ''],
            ['Ana Aluna Fictícia', '01/02/2020', '1A', 'Joana Pessoa Fictícia', '11955554444', ''],
            ['Ana Aluna Fictícia', '01/02/2020', '1A', 'Maria Pessoa Fictícia', '11955553333', ''],
            ['Bruno Aluno Fictício', '01/03/2019', '1A', 'Rita Pessoa Fictícia', '11955552222', ''],
            ['Bruno Aluno Fictício', '01/03/2019', '1A', 'Rita Pessoa Fictícia', '11955552222', '529.982.247-25'],
        ]);
        try {
            [$token, $confirmations, $html] = $this->preparar('responsaveis.xlsx', self::ESCOLA_A1, $file);
        } finally {
            unlink($file);
        }

        $rows = $this->linhasDoLote($token);
        $ana = $this->linhaDe($rows, 'Ana Aluna Fictícia');
        $bruno = $this->linhaDe($rows, 'Bruno Aluno Fictício');
        $message = 'Responsáveis ou contatos divergentes nas linhas repetidas. Nenhum vínculo foi importado; confira na ficha.';
        $this->assertCount(2, $rows);
        $this->assertCount(2, $confirmations);
        $this->assertNull($ana['guardian']);
        $this->assertContains($message, array_column($ana['pending'], 'message'));
        $this->assertSame('529.982.247-25', $bruno['guardian']['cpf']);
        $this->assertNotContains('responsavel_divergente', array_column($bruno['pending'], 'field'));
        $this->assertStringContainsString($message, $html);
        $this->assertStringContainsString('responsáveis divergentes em linhas repetidas (1 aluno)', $html);
        $this->assertStringNotContainsString('Maria Pessoa Fictícia', $html);
        $this->assertStringNotContainsString('Joana Pessoa Fictícia', $html);

        $dump = getenv('ASTUTI_DUMP_TELAS') ?: null;
        if ($dump !== null) {
            $html = str_replace(rtrim((string) config('app.url'), '/').'/', 'file://'.public_path().'/', $html);
            file_put_contents(rtrim($dump, '/').'/importacao_responsavel_divergente.html', preg_replace('/\.(css|js)\?v=\d+/', '.$1', $html));
        }

        $this->post($this->url().'/confirmar', ['lote' => $token, 'confirmados' => $confirmations])->assertRedirect($this->url());
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $this->assertSame(2, Student::query()->count());
            $this->assertSame(2, StudentEnrollment::query()->count());
            $this->assertSame(1, Guardian::query()->count());
            $link = StudentGuardian::query()->with(['student', 'guardian'])->sole();
            $this->assertSame('Bruno Aluno Fictício', $link->student->full_name);
            $this->assertSame('529.982.247-25', $link->guardian->cpf);
        });
    }

    public function test_linhas_repetidas_nao_combinam_cpf_e_telefone_de_homonimos_sem_identificador_comum(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['1A']);
        $this->entrar();
        $file = $this->planilha([
            self::CABECALHO_RESPONSAVEL,
            ['Ana Aluna Fictícia', '01/02/2020', '1A', 'Maria Pessoa Fictícia', '', '529.982.247-25'],
            ['Ana Aluna Fictícia', '01/02/2020', '1A', 'Maria Pessoa Fictícia', '11955553333', ''],
            ['Bruno Aluno Fictício', '01/03/2019', '1A', 'Joana Pessoa Fictícia', '11955554444', ''],
            ['Bruno Aluno Fictício', '01/03/2019', '1A', 'Joana Pessoa Fictícia', '', '111.444.777-35'],
        ]);
        try {
            [$token, $confirmations, $html] = $this->preparar('responsaveis.xlsx', self::ESCOLA_A1, $file);
        } finally {
            unlink($file);
        }

        $rows = $this->linhasDoLote($token);
        $this->assertCount(2, $rows);
        $this->assertCount(2, $confirmations);
        foreach ($rows as $row) {
            $this->assertNull($row['guardian']);
            $this->assertContains('responsavel_divergente', array_column($row['pending'], 'field'));
        }
        $this->assertStringContainsString('responsáveis divergentes em linhas repetidas (2 alunos)', $html);
        foreach (['Maria Pessoa Fictícia', 'Joana Pessoa Fictícia', '529.982.247-25', '111.444.777-35', '11955553333', '11955554444'] as $value) {
            $this->assertStringNotContainsString($value, $html);
        }
        $this->post($this->url().'/confirmar', ['lote' => $token, 'confirmados' => $confirmations])->assertRedirect($this->url());
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $this->assertSame(2, Student::query()->count());
            $this->assertSame(2, StudentEnrollment::query()->count());
            $this->assertSame(0, Guardian::query()->count());
            $this->assertSame(0, StudentGuardian::query()->count());
        });
    }

    public function test_lote_nao_reutiliza_responsavel_com_cpf_distinto_mesmo_nome_e_telefone(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['1A']);
        $this->entrar();

        $file = $this->planilha([
            self::CABECALHO_RESPONSAVEL,
            ['Ana Aluna Fictícia', '01/02/2020', '1A', 'Maria Pessoa Fictícia', '(11) 3333-4444', '529.982.247-25'],
            ['Bruno Aluno Fictício', '01/03/2019', '1A', 'Maria Pessoa Fictícia', '(11) 3333-4444', '111.444.777-35'],
            ['Carla Aluna Fictícia', '01/04/2018', '1A', 'Maria Pessoa Fictícia', '(11) 3333-4444', '529.982.247-25'],
        ]);
        try {
            [$token] = $this->preparar('responsaveis.xlsx', self::ESCOLA_A1, $file);
        } finally {
            unlink($file);
        }

        $this->post($this->url().'/confirmar', ['lote' => $token])->assertRedirect($this->url());

        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $this->assertSame(2, Guardian::query()->count());
            $this->assertSame(3, StudentGuardian::query()->count());
            $porAluno = StudentGuardian::query()->with(['student', 'guardian'])->get()
                ->mapWithKeys(fn (StudentGuardian $link): array => [$link->student->full_name => $link->guardian->cpf])->sortKeys()->all();
            $this->assertSame([
                'Ana Aluna Fictícia' => '529.982.247-25',
                'Bruno Aluno Fictício' => '111.444.777-35',
                'Carla Aluna Fictícia' => '529.982.247-25',
            ], $porAluno);
        });
    }

    public function test_previa_nao_escolhe_responsavel_ambiguo_por_nome_e_telefone_sem_cpf(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['1A']);
        $this->entrar();
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            foreach (['529.982.247-25', '111.444.777-35'] as $cpf) {
                Guardian::query()->create([
                    'id' => (string) Str::uuid(),
                    'full_name' => 'Maria Pessoa Fictícia',
                    'cpf' => $cpf,
                    'phone' => '1133334444',
                ]);
            }
        });
        $file = $this->planilha([
            self::CABECALHO_RESPONSAVEL,
            ['Ana Aluna Fictícia', '01/02/2020', '1A', 'Maria Pessoa Fictícia', '(11) 3333-4444', ''],
            ['Bruno Aluno Fictício', '01/03/2019', '1A', 'Maria Pessoa Fictícia', '(11) 3333-4444', '111.444.777-35'],
        ]);
        try {
            [$token] = $this->preparar('responsaveis.xlsx', self::ESCOLA_A1, $file);
        } finally {
            unlink($file);
        }

        $rows = $this->linhasDoLote($token);
        $ana = $this->linhaDe($rows, 'Ana Aluna Fictícia');
        $this->assertNull($ana['guardian']);
        $this->assertContains('Mais de um responsável corresponde ao nome e telefone. Nenhum vínculo será importado; confira na ficha.', array_column($ana['pending'], 'message'));
        $this->assertNotNull($this->linhaDe($rows, 'Bruno Aluno Fictício')['guardian']);
        $this->post($this->url().'/confirmar', ['lote' => $token])->assertRedirect($this->url());
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $this->assertSame(2, Student::query()->count());
            $this->assertSame(2, StudentEnrollment::query()->count());
            $this->assertSame(2, Guardian::query()->count());
            $link = StudentGuardian::query()->with(['student', 'guardian'])->sole();
            $this->assertSame('Bruno Aluno Fictício', $link->student->full_name);
            $this->assertSame('111.444.777-35', $link->guardian->cpf);
        });
    }

    public function test_previa_considera_cpf_de_responsaveis_do_proprio_lote_independentemente_da_ordem(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['1A']);
        $this->entrar();
        $file = $this->planilha([
            self::CABECALHO_RESPONSAVEL,
            ['Ana Aluna Fictícia', '01/02/2020', '1A', 'Maria Pessoa Fictícia', '(11) 3333-4444', '529.982.247-25'],
            ['Bruno Aluno Fictício', '01/03/2019', '1A', 'Maria Pessoa Fictícia', '(11) 3333-4444', '111.444.777-35'],
            ['Carla Aluna Fictícia', '01/04/2018', '1A', 'Maria Pessoa Fictícia', '(11) 3333-4444', ''],
            ['Davi Aluno Fictício', '01/05/2018', '1A', 'Rita Pessoa Fictícia', '(11) 3333-5555', ''],
            ['Eva Aluna Fictícia', '01/06/2018', '1A', 'Rita Pessoa Fictícia', '(11) 3333-5555', '123.456.789-09'],
            ['Fabio Aluno Fictício', '01/07/2018', '1A', 'Rita Pessoa Fictícia', '(11) 3333-5555', '935.411.347-80'],
        ]);
        try {
            [$token] = $this->preparar('responsaveis.xlsx', self::ESCOLA_A1, $file);
        } finally {
            unlink($file);
        }

        $rows = $this->linhasDoLote($token);
        foreach (['Carla Aluna Fictícia', 'Davi Aluno Fictício'] as $name) {
            $row = $this->linhaDe($rows, $name);
            $this->assertNull($row['guardian']);
            $this->assertContains('Mais de um responsável corresponde ao nome e telefone. Nenhum vínculo será importado; confira na ficha.', array_column($row['pending'], 'message'));
        }
        $this->post($this->url().'/confirmar', ['lote' => $token])->assertRedirect($this->url());
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $this->assertSame(6, Student::query()->count());
            $this->assertSame(6, StudentEnrollment::query()->count());
            $this->assertSame(4, Guardian::query()->count());
            $porAluno = StudentGuardian::query()->with(['student', 'guardian'])->get()
                ->mapWithKeys(fn (StudentGuardian $link): array => [$link->student->full_name => $link->guardian->cpf])->sortKeys()->all();
            $this->assertSame([
                'Ana Aluna Fictícia' => '529.982.247-25',
                'Bruno Aluno Fictício' => '111.444.777-35',
                'Eva Aluna Fictícia' => '123.456.789-09',
                'Fabio Aluno Fictício' => '935.411.347-80',
            ], $porAluno);
        });
    }

    public function test_ambiguidade_de_responsavel_surgida_apos_previa_reverte_lote(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['1A']);
        $this->entrar();
        $createGuardian = function (string $cpf): void {
            app(TenantContext::class)->runFor($this->municipioA, function () use ($cpf): void {
                Guardian::query()->create([
                    'id' => (string) Str::uuid(),
                    'full_name' => 'Maria Pessoa Fictícia',
                    'cpf' => $cpf,
                    'phone' => '1133334444',
                ]);
            });
        };
        $createGuardian('529.982.247-25');
        $file = $this->planilha([
            self::CABECALHO_RESPONSAVEL,
            ['Ana Aluna Fictícia', '01/02/2020', '1A', 'Maria Pessoa Fictícia', '(11) 3333-4444', ''],
        ]);
        try {
            [$token] = $this->preparar('responsaveis.xlsx', self::ESCOLA_A1, $file);
        } finally {
            unlink($file);
        }

        $this->assertNotNull($this->linhasDoLote($token)[0]['guardian']);
        $createGuardian('111.444.777-35');
        $this->post($this->url().'/confirmar', ['lote' => $token])
            ->assertSessionHasErrors(['lote' => 'Mais de um responsável corresponde ao nome e telefone depois da prévia. Refaça a conferência; nenhum registro foi gravado.']);
        $this->assertSame([0, 0, 0], $this->contagens());
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $this->assertSame(2, Guardian::query()->count());
            $this->assertSame(0, StudentGuardian::query()->count());
            $this->assertSame(0, AuditLog::query()->where('action', 'student_import.completed')->count());
        });
    }

    public function test_reimportacao_vincula_responsavel_so_a_quem_nao_tem_e_recusa_cpf_com_outro_nome(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['1A']);
        $this->entrar();

        $primeira = $this->planilha([
            self::CABECALHO_RESPONSAVEL,
            ['Carla Sem Telefone', '05/06/2020', '1A', 'Rita Fictícia Souza', '94023-8059', ''],
            ['Eva Com Tudo', '09/10/2020', '1A', 'Paula Fictícia Reis', '(11) 3333-4444', '111.444.777-35'],
        ]);
        try {
            [$token] = $this->preparar('responsaveis.xlsx', self::ESCOLA_A1, $primeira);
        } finally {
            unlink($primeira);
        }
        $this->post($this->url().'/confirmar', ['lote' => $token])->assertRedirect($this->url());

        $segunda = $this->planilha([
            self::CABECALHO_RESPONSAVEL,
            // Já cadastrada e sem responsável: agora a lista traz o telefone com DDD.
            ['Carla Sem Telefone', '05/06/2020', '1A', 'Rita Fictícia Souza', '(11) 95555-1234', ''],
            // Já tem responsável: nada muda, mesmo com outro nome na lista.
            ['Eva Com Tudo', '09/10/2020', '1A', 'Outra Mãe Fictícia', '(11) 97777-0000', ''],
            // Novo aluno com o CPF da Paula, mas outro nome: não vincula.
            ['Fábio Novo Fictício', '11/11/2020', '1A', 'Pessoa Diferente Fictícia', '(11) 98888-1111', '111.444.777-35'],
        ]);
        try {
            [$token] = $this->preparar('responsaveis.xlsx', self::ESCOLA_A1, $segunda);
        } finally {
            unlink($segunda);
        }

        $rows = $this->linhasDoLote($token);
        $this->assertNotNull($this->linhaDe($rows, 'Carla Sem Telefone')['address_target_id']);
        $this->assertNull($this->linhaDe($rows, 'Eva Com Tudo')['address_target_id']);
        $this->assertNull($this->linhaDe($rows, 'Fábio Novo Fictício')['guardian']);
        $this->assertContains('O CPF informado para Pessoa Diferente Fictícia já está cadastrado na rede com outro nome; o responsável não foi vinculado. Confira o documento.', array_column($this->linhaDe($rows, 'Fábio Novo Fictício')['pending'], 'message'));

        $indice = array_search('Carla Sem Telefone', array_column($rows, 'name'), true);
        $this->post($this->url().'/confirmar', ['lote' => $token, 'completar_enderecos' => [$indice]])
            ->assertRedirect($this->url())
            ->assertSessionHas('status', fn (string $message): bool => str_contains($message, '1 responsável vinculado'));

        $porAluno = app(TenantContext::class)->runFor($this->municipioA, fn (): array => StudentGuardian::query()->with(['student', 'guardian'])->get()
            ->mapWithKeys(fn ($link): array => [$link->student->full_name => $link->guardian->full_name])->sortKeys()->all());
        $this->assertSame([
            'Carla Sem Telefone' => 'Rita Fictícia Souza',
            'Eva Com Tudo' => 'Paula Fictícia Reis',
        ], $porAluno);
    }

    public function test_conferencia_com_dado_do_censo_fora_das_opcoes_e_recusada(): void
    {
        $this->criarTurmas(self::ESCOLA_A1, ['1A']);
        $this->entrar();

        $file = $this->planilha([self::CABECALHO_CENSO, ['Ana Clara Fictícia', '01/02/2020', '1A', 'F', 'Parda', '', '', '', '']]);
        try {
            [$token] = $this->preparar('censo.xlsx', self::ESCOLA_A1, $file);
        } finally {
            unlink($file);
        }

        $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        $payload['rows'][0]['census']['race_color'] = 'azul';
        $adulterado = Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        $this->post($this->url().'/confirmar', ['lote' => $adulterado])
            ->assertSessionHasErrors(['lote' => 'Dados do Censo inválidos na conferência. Reenvie o arquivo.']);
        $this->assertSame([0, 0, 0], $this->contagens());
    }

    public function test_coluna_onibus_da_planilha_marca_o_transporte_na_matricula(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, fn () => School::query()->create([
            'id' => self::ESCOLA_A3, 'name' => 'Escola Rural Fictícia', 'status' => 'active',
        ]));
        $this->criarTurmas(self::ESCOLA_A3, ['MULTI-1', 'MULTI-2']);
        $this->entrar('municipal_manager', []);

        [$lote, $confirmations] = $this->preparar('E03_Rural_Palmeiras.xlsx', self::ESCOLA_A3);
        $this->post($this->url(self::ESCOLA_A3).'/confirmar', ['lote' => $lote, 'confirmados' => $confirmations])
            ->assertRedirect($this->url(self::ESCOLA_A3));

        // A escola rural marca "X" na coluna "Ônibus" de todos os 17 alunos.
        $status = app(TenantContext::class)->runFor($this->municipioA, fn (): array => StudentEnrollment::query()
            ->where('school_id', self::ESCOLA_A3)->pluck('transport_status')->countBy()->all());
        $this->assertSame(['active' => 17], $status);

        // Coluna presente e desmarcada: não solicitado; sem a coluna: sem informação.
        $path = $this->planilha([
            ['Nome', 'Data de nascimento', 'Turma', 'Ônibus'],
            ['Bruna Ônibus Fictícia', '02/02/2020', 'MULTI-1', 'X'],
            ['Caio Sem Ônibus Fictício', '03/03/2020', 'MULTI-1', ''],
        ]);
        [$lote] = $this->preparar('transporte.xlsx', self::ESCOLA_A3, $path);
        $this->post($this->url(self::ESCOLA_A3).'/confirmar', ['lote' => $lote])->assertRedirect($this->url(self::ESCOLA_A3));

        $novos = app(TenantContext::class)->runFor($this->municipioA, fn (): array => StudentEnrollment::query()
            ->join('students', 'students.id', '=', 'student_enrollments.student_id')
            ->whereIn('students.full_name', ['Bruna Ônibus Fictícia', 'Caio Sem Ônibus Fictício'])
            ->pluck('student_enrollments.transport_status', 'students.full_name')->all());
        $this->assertEquals(['Bruna Ônibus Fictícia' => 'active', 'Caio Sem Ônibus Fictício' => 'not_requested'], $novos); // sem ORDER BY: a ordem das chaves não importa
    }

    public function test_mesma_crianca_na_mesma_escola_sem_data_nao_e_cadastrada_de_novo_e_recebe_a_data(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, fn () => School::query()->create([
            'id' => self::ESCOLA_A3, 'name' => 'Escola Rural Fictícia', 'status' => 'active',
        ]));
        $this->criarTurmas(self::ESCOLA_A3, ['MULTI-1', 'MULTI-2']);
        $this->entrar('municipal_manager', []);

        [$lote, $confirmations] = $this->preparar('E03_Rural_Palmeiras.xlsx', self::ESCOLA_A3);
        $this->post($this->url(self::ESCOLA_A3).'/confirmar', ['lote' => $lote, 'confirmados' => $confirmations])
            ->assertRedirect($this->url(self::ESCOLA_A3));
        $this->assertSame([17, 17, 1], $this->contagens());

        // Na planilha, Emanuelly e Ryan vieram só com a idade ("6 anos"); a lista
        // impressa traz a data. Ryan volta de novo sem data.
        $semData = app(TenantContext::class)->runFor($this->municipioA, fn (): array => Student::query()
            ->whereIn('full_name', ['Emanuelly Rodrigues Lopes', 'Ryan Leme Machado'])->whereNull('birth_date')->pluck('id', 'full_name')->all());
        $this->assertCount(2, $semData);

        $path = $this->planilha([
            ['Nome', 'Data de nascimento', 'Turma'],
            ['EMANUELLY RODRIGUES LOPES', '14/06/2020', 'MULTI-1'],
            ['Ryan Leme Machado', '', 'MULTI-1'],
        ]);
        [$lote, , $html] = $this->preparar('lista-impressa.xlsx', self::ESCOLA_A3, $path);
        $rows = json_decode(Crypt::decryptString($lote), true, 512, JSON_THROW_ON_ERROR)['rows'];
        foreach ($rows as $row) {
            $this->assertContains('ja_cadastrado', array_column($row['confirmation'], 'field'), $row['name']);
            $this->assertNotContains('homonimo', array_column($row['confirmation'], 'field'), $row['name']);
        }
        $this->assertSame($semData['Emanuelly Rodrigues Lopes'], $rows[0]['address_target_id']);
        $this->assertStringContainsString('Já cadastrados (serão ignorados)</dt><dd>2</dd>', $html);

        $this->post($this->url(self::ESCOLA_A3).'/confirmar', ['lote' => $lote, 'completar_enderecos' => [0]])
            ->assertRedirect($this->url(self::ESCOLA_A3));

        $this->assertSame([17, 17, 2], $this->contagens());
        $data = app(TenantContext::class)->runFor($this->municipioA, fn () => Student::query()->find($semData['Emanuelly Rodrigues Lopes'])->birth_date?->format('Y-m-d'));
        $this->assertSame('2020-06-14', $data);
    }

    public function test_mesmo_nome_em_outra_escola_continua_sendo_possivel_homonimo(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, fn () => School::query()->create([
            'id' => self::ESCOLA_A3, 'name' => 'Escola Rural Fictícia', 'status' => 'active',
        ]));
        $this->criarTurmas(self::ESCOLA_A3, ['MULTI-1', 'MULTI-2']);
        $this->entrar('municipal_manager', []);

        [$lote, $confirmations] = $this->preparar('E03_Rural_Palmeiras.xlsx', self::ESCOLA_A3);
        $this->post($this->url(self::ESCOLA_A3).'/confirmar', ['lote' => $lote, 'confirmados' => $confirmations]);

        $this->criarTurmas(self::ESCOLA_A1, ['1A']);
        $path = $this->planilha([
            ['Nome', 'Data de nascimento', 'Turma'],
            ['Ryan Leme Machado', '', '1A'],
        ]);
        [$lote] = $this->preparar('outra-escola.xlsx', self::ESCOLA_A1, $path);
        $rows = json_decode(Crypt::decryptString($lote), true, 512, JSON_THROW_ON_ERROR)['rows'];
        $this->assertContains('homonimo', array_column($rows[0]['confirmation'], 'field'));
    }

    private function planilhaComAlunoNovo(array $rows, bool $withAddresses = false): string
    {
        $data = [$withAddresses ? ['Nome', 'Data de nascimento', 'RA', 'Turma', 'Endereço completo'] : ['Nome', 'Data de nascimento', 'RA', 'Turma']];
        foreach ($rows as $row) {
            $data[] = [
                $row['name'],
                $row['birth_date'] === null ? '' : date('d/m/Y', strtotime($row['birth_date'])),
                $row['ra'] ?? '',
                $row['class_name'],
                ...($withAddresses ? [trim(($row['address']['address_street'] ?? '').', '.($row['address']['address_number'] ?? '').' - '.($row['address']['address_district'] ?? ''))] : []),
            ];
        }
        $data[] = ['Nova Aluna Fictícia de Teste', '01/01/2021', '999999999999-1/SP', '1A', ...($withAddresses ? [''] : [])];

        return $this->planilha($data);
    }

    /** Planilha .xlsx mínima (aba "Alunos") com as linhas dadas, a primeira sendo o cabeçalho. */
    private function planilha(array $data): string
    {
        $path = tempnam(sys_get_temp_dir(), 'astuti_xlsx_');
        $this->assertNotFalse($path);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $this->estruturaXlsx($zip);

        $xml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($data as $i => $cells) {
            $number = $i + 1;
            $xml .= '<row r="'.$number.'">';
            foreach ($cells as $j => $value) {
                $ref = chr(65 + $j).$number;
                $xml .= '<c r="'.$ref.'" t="inlineStr"><is><t>'
                    .htmlspecialchars((string) $value, ENT_XML1, 'UTF-8')
                    .'</t></is></c>';
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $this->assertTrue($zip->close());

        return $path;
    }

    private function estruturaXlsx(ZipArchive $zip): void
    {
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Alunos" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>');
    }
}
