<?php

namespace Tests\Feature\Import;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
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
            ->assertRedirect($this->url())->assertSessionHas('status', fn (string $message): bool => str_contains($message, '1 endereço'));

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
    private function planilhaComAlunoNovo(array $rows, bool $withAddresses = false): string
    {
        $path = tempnam(sys_get_temp_dir(), 'astuti_xlsx_');
        $this->assertNotFalse($path);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));

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
}
