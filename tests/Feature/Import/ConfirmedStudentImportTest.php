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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

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

    /** @return array{string, list<int>} */
    private function preparar(string $file, string $schoolId = self::ESCOLA_A1): array
    {
        $response = $this->post($this->url($schoolId).'/previa', [
            'arquivo' => new UploadedFile(self::KIT.$file, $file, null, null, true),
            'school_year_id' => $this->yearId,
        ]);
        $response->assertOk()->assertSee('Conferir antes de importar');
        $html = $response->getContent();

        $this->assertSame(1, preg_match('/name="lote" value="([^"]+)"/', $html, $matches));
        preg_match_all('/name="confirmados\[\]" value="(\d+)"/', $html, $indices);

        return [$matches[1], array_map('intval', $indices[1])];
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

    public function test_aluno_ja_existente_bloqueia_o_lote_inteiro_sem_gravacao_parcial(): void
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
}
