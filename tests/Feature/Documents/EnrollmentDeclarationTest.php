<?php

namespace Tests\Feature\Documents;

use App\Documents\VerificationCode;
use App\Models\Tenant\AttendanceMark;
use App\Models\Tenant\AttendanceSession;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\ClassYearClosure;
use App\Models\Tenant\InstitutionProfile;
use App\Models\Tenant\IssuedDocument;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentFinalResult;
use App\Models\User;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class EnrollmentDeclarationTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private string $studentId;

    private string $enrollmentId;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->studentId, $this->enrollmentId] = $this->inMunicipality(function (): array {
            $year = SchoolYear::query()->create(['id' => (string) Str::uuid(), 'year' => 2027, 'start_date' => '2027-02-01', 'end_date' => '2027-12-18', 'status' => 'active']);
            $class = SchoolClass::query()->create(['id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id, 'name' => '3º Ano B', 'stage' => 'Ensino Fundamental', 'modality' => 'Regular', 'shift' => 'Manhã', 'status' => 'active']);
            $student = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => 'Ana Clara Souza', 'ra' => '123456789-1/SP', 'cpf' => '123.456.789-09', 'birth_date' => '2018-05-16']);
            $enrollment = StudentEnrollment::query()->create(['id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'student_id' => $student->id, 'school_year_id' => $year->id, 'school_class_id' => $class->id, 'status' => 'active']);

            return [$student->id, $enrollment->id];
        });
    }

    private function inMunicipality(callable $callback): mixed
    {
        return app(TenantContext::class)->runFor($this->municipioA, $callback);
    }

    private function user(string $role, array $schools, bool $verified = true): User
    {
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schools);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
        if ($verified) {
            $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', ['code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp())])->assertRedirect();
        } else {
            $this->actingAs($user);
        }

        return $user;
    }

    private function baseUrl(string $school = self::ESCOLA_A1): string
    {
        return '/m/municipio-a/escolas/'.$school.'/alunos/'.$this->studentId.'/declaracoes';
    }

    private function createDocument(): IssuedDocument
    {
        $this->post($this->baseUrl(), ['matricula' => $this->enrollmentId, 'purpose' => 'para fins de comprovação escolar'])->assertRedirect();

        return $this->inMunicipality(fn () => IssuedDocument::query()->where('student_id', $this->studentId)->sole());
    }

    public function test_codigo_tem_dez_caracteres_aleatorios_sem_ambiguidades_e_nome_publico_abreviado(): void
    {
        $codes = [];
        foreach (range(1, 100) as $attempt) {
            $code = VerificationCode::generate();
            $this->assertMatchesRegularExpression('/\A[A-HJ-NP-Z2-9]{10}\z/D', $code);
            $codes[] = $code;
        }
        $this->assertCount(100, array_unique($codes));
        $this->assertSame('Ana C. S.', VerificationCode::abbreviate('Ana Clara Souza'));
    }

    public function test_gestao_emite_documento_com_snapshot_e_auditoria_mascarada(): void
    {
        $this->user('school_secretary', [self::ESCOLA_A1]);
        $this->get($this->baseUrl().'/nova')->assertOk()->assertSee('Emitir declaração de matrícula');
        $document = $this->createDocument();

        $this->assertSame('enrollment_declaration', $document->kind);
        $this->assertSame('Ana Clara Souza', $document->snapshot_student_name);
        $this->assertSame('3º Ano B', $document->snapshot_class_name);
        $this->assertSame('Ensino Fundamental', $document->snapshot_stage);
        $this->assertSame('Manhã', $document->snapshot_shift);
        $this->assertSame('Unidade A', $document->snapshot_school_name);
        $this->assertSame(2027, $document->snapshot_school_year);
        $this->assertSame('para fins de comprovação escolar', $document->purpose);

        $log = $this->inMunicipality(fn () => AuditLog::query()->where('action', 'enrollment_declaration.issued')->sole());
        $this->assertStringNotContainsString($document->verification_code, json_encode($log->after));
        $this->assertStringContainsString('[REDACTED]', json_encode($log->after));

        $this->get($this->baseUrl().'/'.$document->id)->assertOk()
            ->assertSee('Declaramos, para os devidos fins')
            ->assertSee($document->verification_code)
            ->assertSee('<svg', false)
            ->assertSee('declaracao-imprimir.js')
            ->assertDontSee('123456789-1/SP')->assertDontSee('123.456.789-09')->assertDontSee('2018-05-16');
    }

    public function test_impressao_e_verificacao_mantem_dados_da_data_de_emissao(): void
    {
        $this->user('school_director', [self::ESCOLA_A1]);
        $document = $this->createDocument();
        $this->inMunicipality(function (): void {
            Student::query()->findOrFail($this->studentId)->update(['full_name' => 'Nome Alterado Depois']);
        });

        $this->get($this->baseUrl().'/'.$document->id)->assertSee('Ana Clara Souza')->assertDontSee('Nome Alterado Depois');
        config()->set('tenancy.deployed_db_key', 'municipio_a');
        $this->get('/verificar/'.$document->verification_code)->assertOk()->assertSee('Ana C. S.')->assertDontSee('Nome Alterado Depois');
    }

    public function test_consulta_publica_minimizada_noindex_e_sem_login(): void
    {
        $this->user('school_director', [self::ESCOLA_A1]);
        $document = $this->createDocument();
        auth()->logout();
        config()->set('tenancy.deployed_db_key', 'municipio_a');

        $this->get('/verificar/'.$document->verification_code)->assertOk()
            ->assertSee('Documento válido')->assertSee('Declaração de matrícula')
            ->assertSee('Ana C. S.')->assertSee('Unidade A')->assertSee('2027')
            ->assertSee('noindex')->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertDontSee('Ana Clara Souza')->assertDontSee('123456789-1/SP')
            ->assertDontSee('123.456.789-09')->assertDontSee('16/05/2018');

        $this->get('/verificar/ABCDEFGHJK')->assertNotFound()->assertSee('Documento não encontrado');

        // Digitado à mão: minúsculas, espaços e hífens são aceitos.
        $typed = strtolower(substr($document->verification_code, 0, 4).' '.substr($document->verification_code, 4, 3).'-'.substr($document->verification_code, 7));
        $this->get('/verificar/'.rawurlencode($typed))->assertOk()->assertSee('Documento válido');
        $this->get('/verificar')->assertOk()->assertSee('Código de verificação')->assertDontSee('Documento não encontrado');
        $this->get('/verificar?codigo='.rawurlencode($typed))->assertRedirect('/verificar/'.$document->verification_code);
    }

    public function test_sem_chave_configurada_usa_a_unica_prefeitura_do_ambiente(): void
    {
        $this->user('school_director', [self::ESCOLA_A1]);
        $document = $this->createDocument();
        auth()->logout();
        config()->set('tenancy.deployed_db_key', null);
        config()->set('tenancy.connections', array_intersect_key(config('tenancy.connections'), ['municipio_a' => true]));

        $this->get('/verificar/'.$document->verification_code)->assertOk()->assertSee('Documento válido');
    }

    public function test_pagina_publica_nega_configuracao_ausente_e_limita_tentativas_por_ip(): void
    {
        config()->set('tenancy.deployed_db_key', null);
        $this->get('/verificar/ABCDEFGHJK')->assertNotFound();

        // A primeira consulta acima já conta no limite de 20 por minuto.
        config()->set('tenancy.deployed_db_key', 'municipio_a');
        foreach (range(1, 19) as $attempt) {
            $this->get('/verificar/ABCDEFGHJK')->assertNotFound();
        }
        $this->get('/verificar/ABCDEFGHJK')->assertStatus(429);
    }

    public function test_apenas_gestao_da_escola_pode_emitir_listar_imprimir_e_revogar_com_segundo_fator(): void
    {
        $this->user('school_secretary', [self::ESCOLA_A1]);
        $document = $this->createDocument();

        $this->user('teacher', [self::ESCOLA_A1]);
        $this->get($this->baseUrl().'/nova')->assertForbidden();
        $this->get($this->baseUrl().'/'.$document->id)->assertForbidden();
        $this->post($this->baseUrl(), ['matricula' => $this->enrollmentId])->assertForbidden();
        $this->post($this->baseUrl().'/'.$document->id.'/revogar', ['motivo' => 'Documento substituído'])->assertForbidden();
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$this->studentId)->assertDontSee($document->verification_code);

        $this->user('school_director', [self::ESCOLA_A2]);
        $this->get($this->baseUrl().'/nova')->assertForbidden();
        $this->get($this->baseUrl().'/'.$document->id)->assertForbidden();

        $this->user('school_secretary', [self::ESCOLA_A1], false);
        $this->post($this->baseUrl(), ['matricula' => $this->enrollmentId])->assertRedirect(route('two-factor.challenge'));
    }

    public function test_revogacao_exige_motivo_e_mantem_documento_para_verificacao(): void
    {
        $this->user('school_director', [self::ESCOLA_A1]);
        $document = $this->createDocument();
        $url = $this->baseUrl().'/'.$document->id.'/revogar';
        $this->post($url, ['motivo' => ''])->assertSessionHasErrors('motivo');
        $this->post($url, ['motivo' => 'Documento emitido com finalidade incorreta'])->assertRedirect();

        $record = $this->inMunicipality(fn (): array => IssuedDocument::query()->findOrFail($document->id)->only(['revoked_at', 'revoke_reason']));
        $this->assertNotNull($record['revoked_at']);
        $this->assertSame('Documento emitido com finalidade incorreta', $record['revoke_reason']);
        $this->assertSame(1, $this->inMunicipality(fn () => AuditLog::query()->where('action', 'enrollment_declaration.revoked')->count()));
        $this->post($url, ['motivo' => 'Segunda tentativa de revogação'])->assertSessionHasErrors('motivo');

        config()->set('tenancy.deployed_db_key', 'municipio_a');
        $this->get('/verificar/'.$document->verification_code)->assertOk()->assertSee('Documento revogado')->assertDontSee($record['revoke_reason']);
    }

    public function test_nao_emite_apos_matricula_encerrada_nem_aceita_matricula_de_outra_escola(): void
    {
        $this->user('school_secretary', [self::ESCOLA_A1]);
        $this->inMunicipality(function (): void {
            StudentEnrollment::query()->findOrFail($this->enrollmentId)->update(['status' => 'completed']);
        });
        $this->post($this->baseUrl(), ['matricula' => $this->enrollmentId])->assertNotFound();
        $this->post($this->baseUrl(self::ESCOLA_A2), ['matricula' => $this->enrollmentId])->assertForbidden();
    }

    public function test_frequencia_calculada_por_periodo_e_reimpressao_preservada(): void
    {
        $this->travelTo(Carbon::parse('2027-06-15 10:00:00'));
        $this->inMunicipality(function (): void {
            $entry = StudentEnrollment::query()->findOrFail($this->enrollmentId);
            foreach (['present', 'absent', 'justified', 'present'] as $i => $status) {
                $session = AttendanceSession::query()->create([
                    'id' => (string) Str::uuid(), 'school_class_id' => $entry->school_class_id,
                    'attendance_date' => '2027-06-0'.($i + 1), 'recorded_by' => $this->auditFixtureUser->id, 'recorded_at' => now(),
                ]);
                AttendanceMark::query()->create([
                    'id' => (string) Str::uuid(), 'attendance_session_id' => $session->id,
                    'student_id' => $entry->student_id, 'status' => $status,
                ]);
            }
            InstitutionProfile::query()->updateOrCreate(['id' => 1], [
                'department_name' => 'Secretaria Fictícia Original', 'email' => 'original@example.invalid',
            ]);
        });
        $this->user('school_secretary', [self::ESCOLA_A1]);
        $kind = 'attendance_declaration';
        $this->get($this->baseUrl().'/nova?tipo='.$kind)->assertOk()->assertSee('Início do período');
        $payload = ['kind' => $kind, 'matricula' => $this->enrollmentId, 'inicio' => '2027-06-01', 'fim' => '2027-06-10'];
        $this->post($this->baseUrl(), [...$payload, 'fim' => '2027-06-30'])->assertSessionHasErrors('inicio');
        $this->post($this->baseUrl(), [...$payload, 'inicio' => '2027-05-01', 'fim' => '2027-05-31'])->assertSessionHasErrors('inicio');
        $this->post($this->baseUrl(), $payload)->assertRedirect();
        $document = $this->inMunicipality(fn () => IssuedDocument::query()->sole());
        $this->assertSame(4, $document->declaration_snapshot['total']);
        $this->assertSame(2, $document->declaration_snapshot['present']);
        $this->assertEquals(50, $document->declaration_snapshot['percentage']);
        $this->assertSame(1, $document->declaration_snapshot['justified']);
        $url = $this->baseUrl().'/'.$document->id;
        $before = $this->get($url)->assertOk()->assertSee('50,0%')->assertSee('original@example.invalid')
            ->assertSee('doc-timbre', false)->assertSee('doc-rodape', false)->assertSee('<svg', false)->getContent();
        $this->inMunicipality(function (): void {
            Student::query()->findOrFail($this->studentId)->update(['full_name' => 'Nome Fictício Alterado']);
            School::query()->findOrFail(self::ESCOLA_A1)->update(['name' => 'Nome Alterado da Escola']);
            InstitutionProfile::query()->findOrFail(1)->update(['email' => 'novo@example.invalid']);
            AttendanceMark::query()->where('status', 'absent')->firstOrFail()->update(['status' => 'present']);
        });
        $after = $this->get($url)->assertOk()->assertSee('50,0%')->assertSee('Ana Clara Souza')->assertSee('original@example.invalid')
            ->assertDontSee('Nome Fictício Alterado')->assertDontSee('novo@example.invalid')->getContent();
        $this->assertSame(1, preg_match('/<section class="folha declaracao-papel".*?<\/section>/s', $before, $original));
        $this->assertSame(1, preg_match('/<section class="folha declaracao-papel".*?<\/section>/s', $after, $reprint));
        $this->assertSame($original[0], $reprint[0]);
        config()->set('tenancy.deployed_db_key', 'municipio_a');
        $this->get('/verificar/'.$document->verification_code)->assertOk()->assertSee('Declaração de frequência')
            ->assertSee('Ana C. S.')->assertDontSee('50,0%')->assertDontSee('Ana Clara Souza');
        $this->post($url.'/revogar', ['motivo' => 'Substituição da declaração emitida'])->assertRedirect();
        $this->assertSame(1, $this->inMunicipality(fn (): int => AuditLog::query()->where('action', 'attendance_declaration.issued')->count()));
        $this->assertSame(1, $this->inMunicipality(fn (): int => AuditLog::query()->where('action', 'attendance_declaration.revoked')->count()));
        $this->get('/verificar/'.$document->verification_code)->assertOk()->assertSee('Documento revogado')
            ->assertDontSee('Substituição da declaração emitida');
    }

    public function test_transferencia_exige_saida_registrada_e_fica_acessivel_apos_saida(): void
    {
        $this->travelTo(Carbon::parse('2027-06-15 10:00:00'));
        $this->user('school_director', [self::ESCOLA_A1]);
        $payload = ['kind' => 'transfer_declaration', 'matricula' => $this->enrollmentId];
        $this->post($this->baseUrl(), $payload)->assertNotFound();
        $this->inMunicipality(function (): void {
            StudentEnrollment::query()->findOrFail($this->enrollmentId)->update([
                'status' => 'left_network', 'ended_at' => '2027-06-12',
            ]);
        });
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/matriculas?situacao=transferidas')
            ->assertOk()->assertSee('Ana Clara Souza')->assertSee('Declaração de transferência');
        $this->get($this->baseUrl().'/nova?tipo=transfer_declaration')->assertOk()->assertSee('Emitir declaração de transferência');
        $this->post($this->baseUrl(), $payload)->assertRedirect();
        $document = $this->inMunicipality(fn () => IssuedDocument::query()->sole());
        $url = $this->baseUrl().'/'.$document->id;
        $this->get($url)->assertOk()->assertSee('12/06/2027')->assertSee('O histórico escolar será encaminhado')
            ->assertSee('transferência para fora da rede');
        config()->set('tenancy.deployed_db_key', 'municipio_a');
        $this->get('/verificar/'.$document->verification_code)->assertOk()->assertSee('Declaração de transferência')
            ->assertDontSee('12/06/2027')->assertDontSee('transferência para fora da rede');
        $this->user('teacher', [self::ESCOLA_A1]);
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/matriculas?situacao=transferidas')->assertForbidden();
        $this->get($url)->assertForbidden();
        $this->post($this->baseUrl(), $payload)->assertForbidden();
        $this->post($url.'/revogar', ['motivo' => 'Não autorizado'])->assertForbidden();
    }

    public function test_conclusao_exige_fechamento_e_aprovacao_e_snapshot_e_imutavel(): void
    {
        $this->travelTo(Carbon::parse('2027-12-18 10:00:00'));
        $this->user('school_secretary', [self::ESCOLA_A1]);
        $payload = ['kind' => 'completion_declaration', 'matricula' => $this->enrollmentId];
        $this->post($this->baseUrl(), $payload)->assertSessionHasErrors('matricula');
        $this->inMunicipality(function (): void {
            $entry = StudentEnrollment::query()->findOrFail($this->enrollmentId);
            StudentFinalResult::query()->create([
                'id' => (string) Str::uuid(), 'school_class_id' => $entry->school_class_id,
                'student_enrollment_id' => $entry->id, 'result' => 'retido', 'suggested_result' => 'retido',
                'confirmed_at' => now(), 'confirmed_by' => $this->auditFixtureUser->id,
            ]);
            ClassYearClosure::query()->findOrFail($entry->school_class_id)->forceFill([
                'closed_at' => now(), 'closed_by' => $this->auditFixtureUser->id, 'close_reason' => 'Fechamento fictício',
            ])->save();
        });
        $this->post($this->baseUrl(), $payload)->assertSessionHasErrors('matricula');
        $this->inMunicipality(function (): void {
            $entry = StudentEnrollment::query()->findOrFail($this->enrollmentId);
            $closure = ClassYearClosure::query()->findOrFail($entry->school_class_id);
            $closure->forceFill(['closed_at' => null, 'closed_by' => null, 'close_reason' => null])->save();
            StudentFinalResult::query()->sole()->update(['result' => 'aprovado', 'suggested_result' => 'aprovado']);
            $closure->forceFill(['closed_at' => now(), 'closed_by' => $this->auditFixtureUser->id, 'close_reason' => 'Fechamento fictício'])->save();
        });
        $this->post($this->baseUrl(), $payload)->assertRedirect();
        $document = $this->inMunicipality(fn () => IssuedDocument::query()->sole());
        $this->get($this->baseUrl().'/'.$document->id)->assertOk()->assertSee('Declaração de conclusão')->assertSee('3º Ano B')
            ->assertSee('resultado confirmado de aprovação');
        config()->set('tenancy.deployed_db_key', 'municipio_a');
        $this->get('/verificar/'.$document->verification_code)->assertOk()->assertSee('Declaração de conclusão')
            ->assertDontSee('resultado confirmado de aprovação');
        $this->expectException(QueryException::class);
        $this->inMunicipality(fn () => IssuedDocument::query()->findOrFail($document->id)->update(['snapshot_student_name' => 'Alteração proibida']));
    }
}
