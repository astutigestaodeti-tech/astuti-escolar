<?php

namespace Tests\Feature\Import;

use App\Models\Tenant\AuditLog;
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

class ConfirmedTransportImportTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private string $yearId;

    private string $studentA;

    private string $studentB;

    private string $enrollmentA;

    private string $enrollmentB;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->yearId, $this->studentA, $this->studentB, $this->enrollmentA, $this->enrollmentB] = app(TenantContext::class)
            ->runFor($this->municipioA, function (): array {
                $year = SchoolYear::query()->create([
                    'id' => (string) Str::uuid(), 'year' => 2027, 'start_date' => '2027-02-01',
                    'end_date' => '2027-12-18', 'status' => 'active',
                ]);

                $first = Student::query()->create([
                    'id' => (string) Str::uuid(), 'full_name' => 'João Ferreira',
                    'birth_date' => '2017-01-01', 'ra' => '999000000001-6/SP',
                ]);
                $second = Student::query()->create([
                    'id' => (string) Str::uuid(), 'full_name' => 'Érica Silva',
                    'birth_date' => '2018-01-01', 'ra' => '999000000002-8/SP',
                ]);

                $enrollmentA = StudentEnrollment::query()->create([
                    'id' => (string) Str::uuid(), 'student_id' => $first->id,
                    'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id, 'status' => 'active',
                ]);
                $enrollmentB = StudentEnrollment::query()->create([
                    'id' => (string) Str::uuid(), 'student_id' => $second->id,
                    'school_id' => self::ESCOLA_A2, 'school_year_id' => $year->id, 'status' => 'active',
                ]);

                return [$year->id, $first->id, $second->id, $enrollmentA->id, $enrollmentB->id];
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

    private function url(string $school = self::ESCOLA_A1): string
    {
        return '/m/municipio-a/escolas/'.$school.'/importacoes/transporte';
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('transporte-ficticio.csv', $content);
    }

    private function previa(string $content, string $school = self::ESCOLA_A1): string
    {
        $response = $this->post($this->url($school).'/previa', [
            'arquivo' => $this->csv($content), 'school_year_id' => $this->yearId,
        ]);
        $response->assertOk()->assertSee('Conferir transporte');
        $this->assertSame(1, preg_match('/name="lote" value="([^"]+)"/', $response->getContent(), $match));

        return $match[1];
    }

    private function transportStatus(string $id): ?string
    {
        return app(TenantContext::class)->runFor($this->municipioA, fn (): ?string => StudentEnrollment::query()->findOrFail($id)->transport_status);
    }

    public function test_previa_nao_altera_nada_confirmacao_auditada_e_reenvio_bloqueado(): void
    {
        $user = $this->entrar();
        $csv = "RA;NOME;SITUAÇÃO\n999000000001-6/SP;João Ferreira;SIM\n";
        $token = $this->previa(iconv('UTF-8', 'Windows-1252', $csv));
        $this->assertNull($this->transportStatus($this->enrollmentA));

        $this->post($this->url().'/confirmar', ['lote' => $token])
            ->assertRedirect($this->url())->assertSessionHas('status');
        $this->assertSame('active', $this->transportStatus($this->enrollmentA));
        $this->assertNull($this->transportStatus($this->enrollmentB));

        $results = app(TenantContext::class)->runFor($this->municipioA, fn (): array => [
            Student::query()->count(),
            DB::connection('tenant')->table('transport_import_receipts')->count(),
            AuditLog::query()->where('auditable_type', StudentEnrollment::class)->where('action', 'updated')
                ->where('user_id', $user->id)->count(),
            AuditLog::query()->where('action', 'transport_import.completed')->count(),
        ]);
        $this->assertSame([2, 1, 1, 1], $results);
        $audit = app(TenantContext::class)->runFor($this->municipioA, fn (): AuditLog => AuditLog::query()
            ->where('auditable_type', StudentEnrollment::class)->where('action', 'updated')
            ->where('user_id', $user->id)->firstOrFail());
        $this->assertArrayHasKey('transport_status', $audit->before);
        $this->assertNull($audit->before['transport_status']);
        $this->assertSame('active', $audit->after['transport_status']);

        $this->post($this->url().'/confirmar', ['lote' => $token])->assertSessionHasErrors('lote');
        $this->assertSame('active', $this->transportStatus($this->enrollmentA));
    }

    public function test_cancelamento_impede_reativacao_pela_importacao(): void
    {
        $this->entrar();
        $token = $this->previa("RA;SITUACAO\n999000000001-6/SP;CANCELADO\n");
        $this->post($this->url().'/confirmar', ['lote' => $token])->assertRedirect($this->url());
        $this->assertSame('cancelled', $this->transportStatus($this->enrollmentA));

        $this->post($this->url().'/previa', [
            'arquivo' => $this->csv("RA;SITUACAO\n999000000001-6/SP;ATIVO\n"),
            'school_year_id' => $this->yearId,
        ])->assertSessionHasErrors('arquivo');
        $this->assertSame('cancelled', $this->transportStatus($this->enrollmentA));
    }

    public function test_ra_de_outra_escola_impede_gravacao_e_nao_revela_nome(): void
    {
        $this->entrar();
        $response = $this->post($this->url().'/previa', [
            'arquivo' => $this->csv("RA;SITUACAO\n999000000002-8/SP;SIM\n"),
            'school_year_id' => $this->yearId,
        ]);
        $response->assertSessionHasErrors('arquivo');
        $this->assertNull($this->transportStatus($this->enrollmentA));
        $this->assertNull($this->transportStatus($this->enrollmentB));
    }

    public function test_sem_permissao_ou_segundo_fator_confirmado_nao_importa(): void
    {
        $this->entrar('teacher');
        $this->get($this->url())->assertForbidden();
        $this->post($this->url().'/previa', [])->assertForbidden();

        $this->entrar('school_secretary', [self::ESCOLA_A2]);
        $this->get($this->url())->assertForbidden();

        $user = $this->criarUsuario('sem-2fa-transporte@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);
        $this->actingAs($user)->get($this->url())->assertRedirect('/seguranca/segundo-fator/desafio');
        $this->actingAs($user)->post($this->url().'/confirmar', ['lote' => 'x'])->assertForbidden();
        $this->assertNull($this->transportStatus($this->enrollmentA));
    }

    public function test_alteracao_concorrente_da_matricula_recusa_lote_inteiro(): void
    {
        $this->entrar();
        $token = $this->previa("RA;SITUACAO\n999000000001-6/SP;SIM\n");
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            StudentEnrollment::query()->findOrFail($this->enrollmentA)->update(['started_at' => '2027-02-02']);
        });

        $this->post($this->url().'/confirmar', ['lote' => $token])->assertSessionHasErrors('lote');
        $this->assertNull($this->transportStatus($this->enrollmentA));
    }

    public function test_conflito_na_segunda_linha_desfaz_alteracao_da_primeira(): void
    {
        $third = app(TenantContext::class)->runFor($this->municipioA, function (): string {
            $student = Student::query()->create([
                'id' => (string) Str::uuid(), 'full_name' => 'Álvaro Monteiro',
                'birth_date' => '2018-01-01', 'ra' => '999000000003-0/SP',
            ]);

            return StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $student->id,
                'school_id' => self::ESCOLA_A1, 'school_year_id' => $this->yearId, 'status' => 'active',
            ])->id;
        });
        $this->entrar();
        $token = $this->previa("RA;SITUACAO\n999000000001-6/SP;SIM\n999000000003-0/SP;SIM\n");
        app(TenantContext::class)->runFor($this->municipioA, function () use ($third): void {
            StudentEnrollment::query()->findOrFail($third)->update(['started_at' => '2027-02-03']);
        });

        $this->post($this->url().'/confirmar', ['lote' => $token])->assertSessionHasErrors('lote');
        $this->assertNull($this->transportStatus($this->enrollmentA));
        $this->assertNull($this->transportStatus($third));
        $receipts = app(TenantContext::class)->runFor($this->municipioA, fn (): int => DB::connection('tenant')->table('transport_import_receipts')->count());
        $this->assertSame(0, $receipts);
        $this->assertSame(0, app(TenantContext::class)->runFor($this->municipioA, fn (): int => AuditLog::query()
            ->where('auditable_type', StudentEnrollment::class)->where('action', 'updated')
            ->where('auditable_id', $this->enrollmentA)->count()));
    }

    public function test_token_alterado_e_token_de_outra_conta_sao_recusados(): void
    {
        $this->entrar();
        $token = $this->previa("RA;SITUACAO\n999000000001-6/SP;SIM\n");
        $this->post($this->url().'/confirmar', ['lote' => $token.'x'])->assertSessionHasErrors('lote');

        $this->entrar();
        $this->post($this->url().'/confirmar', ['lote' => $token])->assertSessionHasErrors('lote');
        $this->assertNull($this->transportStatus($this->enrollmentA));
    }

    public function test_csv_com_ra_repetido_nao_atualiza_registros(): void
    {
        $this->entrar();
        $this->post($this->url().'/previa', [
            'arquivo' => $this->csv("RA;SITUACAO\n999000000001-6/SP;SIM\n999000000001-6/SP;CANCELADO\n"),
            'school_year_id' => $this->yearId,
        ])->assertSessionHasErrors('arquivo');
        $this->assertNull($this->transportStatus($this->enrollmentA));
    }

    public function test_matriculas_ativas_ambiguas_sao_bloqueadas_na_previa_e_na_confirmacao(): void
    {
        $this->entrar();
        $token = $this->previa("RA;SITUACAO\n999000000001-6/SP;SIM\n");
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $this->studentA,
                'school_id' => self::ESCOLA_A1, 'school_year_id' => $this->yearId, 'status' => 'active',
            ]);
        });

        $this->from($this->url().'/previa')->post($this->url().'/confirmar', ['lote' => $token])
            ->assertRedirect($this->url())->assertSessionHasErrors('lote');
        $this->from($this->url().'/previa')->post($this->url().'/previa', [
            'arquivo' => $this->csv("RA;SITUACAO\n999000000001-6/SP;SIM\n"),
            'school_year_id' => $this->yearId,
        ])->assertRedirect($this->url())->assertSessionHasErrors('arquivo');
        $this->assertNull($this->transportStatus($this->enrollmentA));
    }

    public function test_erros_de_validacao_voltam_ao_formulario_get_em_vez_de_rota_post(): void
    {
        $this->entrar();

        $this->from($this->url().'/previa')->post($this->url().'/previa', [
            'arquivo' => $this->csv("RA;Status;Situação do Transporte\n999000000001-6/SP;ATIVO;CANCELADO\n"),
            'school_year_id' => $this->yearId,
        ])->assertRedirect($this->url())->assertSessionHasErrors('arquivo');

        $this->from($this->url().'/previa')->post($this->url().'/confirmar', [
            'lote' => 'previsao-alterada',
        ])->assertRedirect($this->url())->assertSessionHasErrors('lote');

        $this->get($this->url())->assertOk()->assertSee('O lote não foi aplicado.');
    }
}
