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
        // Sufixo após o padding base64 pode ser ignorado pelo decodificador.
        // Alterar um byte interno invalida a autenticação do conteúdo cifrado.
        $alteredToken = ($token[0] === 'A' ? 'B' : 'A').substr($token, 1);
        $this->post($this->url().'/confirmar', ['lote' => $alteredToken])->assertSessionHasErrors('lote');

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

        // O driver array do phpunit não persiste a sessão entre requisições.
        // O POST acima verifica o erro; este GET verifica a rota de retorno.
        $this->get($this->url())->assertOk()->assertSee('Transporte escolar');
    }

    public function test_lista_da_secretaria_sem_ra_acha_pelo_nome_e_data_e_exige_conferencia(): void
    {
        $user = $this->entrar();
        $csv = "NOME ALUNO;DT NASC;ESCOLA;LINHA;PONTO EMBARQUE;ADAPTADO;SITUACAO\n"
            ."JOAO FERREIRA;01/01/2017;EMEF A1;LINHA 01;RUA FICTICIA, 10;SIM;ATIVO\n"
            ."ERICA SILVA;01/01/2018;EMEF A2;LINHA 02;RUA FICTICIA, 20;;ATIVO\n"
            ."JOAO FERREIRA;02/02/2016;EMEF A1;LINHA 01;RUA FICTICIA, 30;;CANCELADO\n";
        $response = $this->post($this->url().'/previa', [
            'arquivo' => $this->csv(iconv('UTF-8', 'Windows-1252', $csv)), 'school_year_id' => $this->yearId,
        ])->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('Achados pelo nome (confira)', $html);
        $this->assertStringContainsString('Nome e data de nascimento', $html);
        $this->assertStringContainsString('Linhas que ficam de fora', $html);
        $this->assertStringContainsString('EMEF A2', $html);
        $this->assertSame(2, substr_count($html, 'Não encontrado entre as matrículas ativas desta escola neste ano.'));
        $this->assertStringContainsString('RUA FICTICIA, 10', $html);
        $this->assertStringNotContainsString('RUA FICTICIA, 20', $html, 'Endereço de outra escola não aparece na prévia.');
        $this->assertStringNotContainsString('RUA FICTICIA, 30', $html, 'Linha ignorada não mostra ponto de embarque.');
        $this->assertStringContainsString('name="conferido_nomes"', $html);
        $this->assertSame(1, preg_match('/name="lote" value="([^"]+)"/', $html, $match));

        // Sem marcar a conferencia dos alunos achados pelo nome, nada muda.
        $this->post($this->url().'/confirmar', ['lote' => $match[1]])->assertSessionHasErrors('conferido_nomes');
        $this->assertNull($this->transportStatus($this->enrollmentA));

        $this->post($this->url().'/confirmar', ['lote' => $match[1], 'conferido_nomes' => '1'])
            ->assertRedirect($this->url())->assertSessionHas('status');
        $this->assertSame('active', $this->transportStatus($this->enrollmentA));
        $this->assertNull($this->transportStatus($this->enrollmentB), 'Aluno de outra escola nao muda.');
        $this->assertSame(1, app(TenantContext::class)->runFor($this->municipioA, fn (): int => AuditLog::query()
            ->where('auditable_type', StudentEnrollment::class)->where('action', 'updated')->where('user_id', $user->id)->count()));
    }

    public function test_homonimo_com_mesma_data_fica_de_fora_e_pede_ra(): void
    {
        $this->entrar();
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $twin = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => 'João Ferreira', 'birth_date' => '2017-01-01']);
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $twin->id,
                'school_id' => self::ESCOLA_A1, 'school_year_id' => $this->yearId, 'status' => 'active',
            ]);
        });

        $this->from($this->url())->post($this->url().'/previa', [
            'arquivo' => $this->csv("NOME;DT NASC;SITUACAO\nJoão Ferreira;01/01/2017;SIM\n"), 'school_year_id' => $this->yearId,
        ])->assertRedirect($this->url())->assertSessionHasErrors('arquivo');

        $html = $this->post($this->url().'/previa', [
            'arquivo' => $this->csv("RA;NOME;DT NASC;SITUACAO\n999000000001-6/SP;João Ferreira;01/01/2017;SIM\n;João Ferreira;01/01/2017;NÃO\n"),
            'school_year_id' => $this->yearId,
        ])->assertOk()->getContent();
        $this->assertStringContainsString('Mais de um aluno desta escola com este nome e data. Use o RA.', $html);
        $this->assertStringNotContainsString('name="conferido_nomes"', $html, 'Achado pelo RA nao pede conferencia por nome.');
    }

    public function test_cancelado_na_lista_mista_fica_de_fora_sem_travar_os_demais(): void
    {
        $this->entrar();
        app(TenantContext::class)->runFor($this->municipioA, fn () => StudentEnrollment::query()->findOrFail($this->enrollmentA)->update(['transport_status' => 'cancelled']));
        $html = $this->post($this->url().'/previa', [
            'arquivo' => $this->csv("RA;SITUACAO\n999000000001-6/SP;ATIVO\n999000000002-8/SP;SIM\n"),
            'school_year_id' => $this->yearId,
        ]);
        // Nenhuma linha aplicavel: cancelado nao reativa e o outro RA e de outra escola.
        $html->assertSessionHasErrors('arquivo');
        $this->assertSame('cancelled', $this->transportStatus($this->enrollmentA));
    }

    public function test_previa_e_gravacao_salvam_linha_ponto_e_adaptado_com_auditoria(): void
    {
        $user = $this->entrar();
        $csv = "RA;SITUACAO;LINHA;PONTO EMBARQUE;ADAPTADO\n"
            ."999000000001-6/SP;ATIVO;Linha 09;Praça das Acácias;X\n";
        $response = $this->post($this->url().'/previa', [
            'arquivo' => $this->csv($csv), 'school_year_id' => $this->yearId,
        ])->assertOk()->assertSee('Linha 09')->assertSee('Praça das Acácias')->assertSee('Veículo adaptado');

        $this->assertSame(1, preg_match('/name="lote" value="([^"]+)"/', $response->getContent(), $match));
        $this->assertNull($this->transportStatus($this->enrollmentA));
        $this->post($this->url().'/confirmar', ['lote' => $match[1]])
            ->assertRedirect($this->url())->assertSessionHas('status');

        $stored = app(TenantContext::class)->runFor($this->municipioA, function (): array {
            $e = StudentEnrollment::query()->findOrFail($this->enrollmentA);

            return [$e->transport_status, $e->transport_route, $e->transport_stop, $e->transport_adapted_vehicle];
        });
        $this->assertSame(['active', 'Linha 09', 'Praça das Acácias', true], $stored);

        $audit = app(TenantContext::class)->runFor($this->municipioA, fn (): AuditLog => AuditLog::query()
            ->where('auditable_type', StudentEnrollment::class)->where('auditable_id', $this->enrollmentA)
            ->where('action', 'updated')->where('user_id', $user->id)->latest('occurred_at')->firstOrFail());
        $this->assertNull($audit->before['transport_route']);
        $this->assertSame('Linha 09', $audit->after['transport_route']);
        $this->assertSame('[REDACTED]', $audit->after['transport_stop']);
        $this->assertSame('[REDACTED]', $audit->after['transport_adapted_vehicle']);
    }

    public function test_planilha_com_campos_vazios_preserva_detalhes_e_aceita_nao_explicito(): void
    {
        $this->entrar();
        $token = $this->previa("RA;SITUACAO;ROTA;PONTO;VEICULO ADAPTADO\n"
            ."999000000001-6/SP;SIM;Linha 05;Parada Fictícia;S\n");
        $this->post($this->url().'/confirmar', ['lote' => $token])->assertSessionHas('status');

        $emptyToken = $this->previa("RA;SITUACAO;LINHA;PONTO;ADAPTADO\n999000000001-6/SP;SIM;;;\n");
        $this->post($this->url().'/confirmar', ['lote' => $emptyToken])
            ->assertRedirect($this->url())->assertSessionHas('status', '0 matrícula(s) atualizada(s) com auditoria.');

        $stored = app(TenantContext::class)->runFor($this->municipioA, fn (): StudentEnrollment => StudentEnrollment::query()->findOrFail($this->enrollmentA));
        $this->assertSame('Linha 05', $stored->transport_route);
        $this->assertSame('Parada Fictícia', $stored->transport_stop);
        $this->assertTrue($stored->transport_adapted_vehicle);

        $noToken = $this->previa("RA;SITUACAO;VEÍCULO ADAPTADO\n999000000001-6/SP;SIM;NÃO\n");
        $this->post($this->url().'/confirmar', ['lote' => $noToken])->assertSessionHas('status');
        $stored = app(TenantContext::class)->runFor($this->municipioA, fn (): StudentEnrollment => StudentEnrollment::query()->findOrFail($this->enrollmentA));
        $this->assertFalse($stored->transport_adapted_vehicle);
        $this->assertSame('Linha 05', $stored->transport_route);
        $this->assertSame('Parada Fictícia', $stored->transport_stop);
    }

    public function test_concorrencia_dos_detalhes_desfaz_lote_e_preserva_auditoria(): void
    {
        $this->entrar();
        $token = $this->previa("RA;SITUACAO;LINHA;PONTO\n999000000001-6/SP;SIM;Linha 03;Rua Fictícia\n");
        app(TenantContext::class)->runFor($this->municipioA, fn () => StudentEnrollment::query()
            ->findOrFail($this->enrollmentA)->update(['transport_route' => 'Linha 04']));

        $this->post($this->url().'/confirmar', ['lote' => $token])->assertSessionHasErrors('lote');
        $stored = app(TenantContext::class)->runFor($this->municipioA, fn (): StudentEnrollment => StudentEnrollment::query()->findOrFail($this->enrollmentA));
        $this->assertSame('Linha 04', $stored->transport_route);
        $this->assertNull($stored->transport_stop);
        $this->assertNull($stored->transport_status);
        $this->assertSame(0, app(TenantContext::class)->runFor($this->municipioA, fn (): int => DB::connection('tenant')
            ->table('transport_import_receipts')->count()));
    }
}
