<?php

namespace Tests\Feature\Students;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\Guardian;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentGuardian;
use App\Models\User;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class GuardianWriteTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private string $studentA;

    private string $studentSibling;

    private string $studentOtherSchool;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->studentA, $this->studentSibling, $this->studentOtherSchool] = $this->inMunicipality(function (): array {
            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(),
                'year' => 2027,
                'start_date' => '2027-02-01',
                'end_date' => '2027-12-18',
                'status' => 'active',
            ]);

            $students = [];
            foreach ([self::ESCOLA_A1, self::ESCOLA_A1, self::ESCOLA_A2] as $index => $schoolId) {
                $student = Student::query()->create([
                    'id' => (string) Str::uuid(),
                    'full_name' => 'Aluno fictício '.($index + 1),
                ]);
                StudentEnrollment::query()->create([
                    'id' => (string) Str::uuid(),
                    'student_id' => $student->id,
                    'school_id' => $schoolId,
                    'school_year_id' => $year->id,
                    'status' => 'active',
                ]);
                $students[] = $student->id;
            }

            return $students;
        });
    }

    private function inMunicipality(callable $callback): mixed
    {
        return app(TenantContext::class)->runFor($this->municipioA, $callback);
    }

    private function userWithRole(string $role, array $schools = [], bool $otherMunicipality = false): User
    {
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $otherMunicipality ? $this->municipioB : $this->municipioA, $role, $schools);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();

        return $user;
    }

    private function verifyTwoFactor(User $user): void
    {
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp()),
        ])->assertRedirect();
    }

    private function url(?string $student = null, string $school = self::ESCOLA_A1): string
    {
        return '/m/municipio-a/escolas/'.$school.'/alunos/'.($student ?? $this->studentA).'/responsaveis';
    }

    private function data(array $override = []): array
    {
        return array_replace([
            'full_name' => 'Maria da Silva Fictícia',
            'cpf' => '123.456.789-09',
            'phone' => '(11) 98765-4321',
            'email' => 'maria@example.invalid',
            'relationship' => 'mother',
            'is_legal_guardian' => '1',
            'is_primary_contact' => '1',
        ], $override);
    }

    public function test_secretaria_cria_responsavel_e_vinculo_com_auditoria_redigida(): void
    {
        $this->verifyTwoFactor($this->userWithRole('school_secretary', [self::ESCOLA_A1]));
        $this->get($this->url().'/novo')->assertOk()->assertSee('Adicionar responsável')->assertSee('CPF')->assertSee('data-guardian-phone')->assertSee('js/guardian-phone.js');
        $this->post($this->url(), $this->data())->assertRedirect('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$this->studentA);

        [$guardian, $link, $logGuardian, $logLink] = $this->inMunicipality(fn (): array => [
            Guardian::query()->sole(),
            StudentGuardian::query()->sole(),
            AuditLog::query()->where('auditable_type', Guardian::class)->where('action', 'created')->sole(),
            AuditLog::query()->where('auditable_type', StudentGuardian::class)->where('action', 'created')->sole(),
        ]);

        $this->assertSame('123.456.789-09', $guardian->cpf);
        $this->assertSame('11987654321', $guardian->phone);
        $this->assertSame($this->studentA, $link->student_id);
        $this->assertSame('mother', $link->relationship);
        $this->assertTrue($link->is_legal_guardian);
        $this->assertTrue($link->is_primary_contact);
        $this->assertNull($logGuardian->before);
        $this->assertSame('Maria da Silva Fictícia', $logGuardian->after['full_name']);
        $this->assertSame('[REDACTED]', $logGuardian->after['cpf']);
        $this->assertSame('[REDACTED]', $logGuardian->after['phone']);
        $this->assertSame('mother', $logLink->after['relationship']);
        $this->assertSame($this->studentA, $logLink->after['student_id']);
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$this->studentA)
            ->assertOk()
            ->assertSee('Responsáveis')
            ->assertSee('Maria da Silva Fictícia')
            ->assertSee('(11) 98765-4321')
            ->assertSee('Adicionar responsável')
            ->assertSee('Editar');
    }

    public function test_cpf_ja_existente_recusa_nome_divergente_e_atualiza_contatos_dos_irmaos(): void
    {
        $this->verifyTwoFactor($this->userWithRole('school_director', [self::ESCOLA_A1]));
        $this->post($this->url(), $this->data())->assertRedirect();

        $this->from($this->url($this->studentSibling).'/novo')
            ->post($this->url($this->studentSibling), $this->data([
                'full_name' => 'Pessoa fictícia diferente',
                'phone' => '(11) 99999-0000',
            ]))->assertSessionHasErrors([
                'cpf' => 'Este CPF já está cadastrado na rede com outro nome. Confira o documento.',
            ]);

        $this->assertSame(1, $this->inMunicipality(fn (): int => Guardian::query()->count()));
        $this->assertSame(1, $this->inMunicipality(fn (): int => StudentGuardian::query()->count()));
        $this->assertSame('11987654321', $this->inMunicipality(fn (): string => Guardian::query()->sole()->phone));

        $this->post($this->url($this->studentSibling), $this->data([
            'full_name' => '  MARIA   DA SILVA  FICTICIA ',
            'phone' => '(11) 99999-0000',
            'email' => 'contato-atualizado@example.invalid',
        ]))->assertSessionHas('status', 'Responsável já cadastrado na rede: o vínculo foi feito e os dados de contato foram atualizados.');

        [$guardians, $links, $guardian, $created, $updated] = $this->inMunicipality(fn (): array => [
            Guardian::query()->count(),
            StudentGuardian::query()->count(),
            Guardian::query()->sole(),
            AuditLog::query()->where('auditable_type', Guardian::class)->where('action', 'created')->count(),
            AuditLog::query()->where('auditable_type', Guardian::class)->where('action', 'updated')->sole(),
        ]);

        $this->assertSame(1, $guardians);
        $this->assertSame(2, $links);
        $this->assertSame('Maria da Silva Fictícia', $guardian->full_name);
        $this->assertSame('11999990000', $guardian->phone);
        $this->assertSame('contato-atualizado@example.invalid', $guardian->email);
        $this->assertSame(1, $created);
        $this->assertSame('[REDACTED]', $updated->before['phone']);
        $this->assertSame('[REDACTED]', $updated->after['phone']);
        $this->assertSame('maria@example.invalid', $updated->before['email']);
        $this->assertSame('contato-atualizado@example.invalid', $updated->after['email']);

        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$this->studentA)
            ->assertSee('(11) 99999-0000');

        $this->from($this->url().'/novo')->post($this->url(), $this->data())
            ->assertSessionHasErrors(['cpf' => 'Este responsável já está vinculado ao aluno. Use Editar na ficha.']);
    }

    public function test_reuso_do_cpf_sem_novo_contato_mantem_telefone_e_email_existentes(): void
    {
        $this->verifyTwoFactor($this->userWithRole('school_director', [self::ESCOLA_A1]));
        $this->post($this->url(), $this->data())->assertRedirect();
        $this->post($this->url($this->studentSibling), $this->data([
            'phone' => '', 'email' => '', 'is_primary_contact' => '0',
        ]))->assertRedirect();

        [$phone, $email, $updates] = $this->inMunicipality(fn (): array => [
            Guardian::query()->sole()->phone,
            Guardian::query()->sole()->email,
            AuditLog::query()->where('auditable_type', Guardian::class)->where('action', 'updated')->count(),
        ]);
        $this->assertSame('11987654321', $phone);
        $this->assertSame('maria@example.invalid', $email);
        $this->assertSame(0, $updates);
    }

    public function test_edicao_registra_antes_depois_sem_expor_cpf_ou_telefone(): void
    {
        $this->verifyTwoFactor($this->userWithRole('municipal_manager'));
        $this->post($this->url(), $this->data())->assertRedirect();
        $linkId = $this->inMunicipality(fn (): string => StudentGuardian::query()->sole()->id);

        $this->get($this->url().'/'.$linkId.'/editar')->assertOk()->assertSee('Editar responsável');
        $this->put($this->url().'/'.$linkId, $this->data([
            'full_name' => 'Maria da Silva Atualizada',
            'phone' => '1133334444',
            'relationship' => 'grandmother',
            'is_primary_contact' => '0',
        ]))->assertRedirect();

        [$guardian, $link, $personAudit, $linkAudit] = $this->inMunicipality(fn (): array => [
            Guardian::query()->sole(),
            StudentGuardian::query()->sole(),
            AuditLog::query()->where('auditable_type', Guardian::class)->where('action', 'updated')->sole(),
            AuditLog::query()->where('auditable_type', StudentGuardian::class)->where('action', 'updated')->sole(),
        ]);

        $this->assertSame('Maria da Silva Atualizada', $guardian->full_name);
        $this->assertSame('1133334444', $guardian->phone);
        $this->assertSame('grandmother', $link->relationship);
        $this->assertFalse($link->is_primary_contact);
        $this->assertSame('Maria da Silva Fictícia', $personAudit->before['full_name']);
        $this->assertSame('Maria da Silva Atualizada', $personAudit->after['full_name']);
        $this->assertSame('[REDACTED]', $personAudit->before['phone']);
        $this->assertSame('[REDACTED]', $personAudit->after['phone']);
        $this->assertSame('[REDACTED]', $personAudit->before['cpf']);
        $this->assertSame('[REDACTED]', $personAudit->after['cpf']);
        $this->assertSame('mother', $linkAudit->before['relationship']);
        $this->assertSame('grandmother', $linkAudit->after['relationship']);
        $this->assertTrue($linkAudit->before['is_primary_contact']);
        $this->assertFalse($linkAudit->after['is_primary_contact']);
    }

    public function test_troca_contato_principal_na_criacao_e_edicao_com_duas_auditorias(): void
    {
        $this->verifyTwoFactor($this->userWithRole('school_secretary', [self::ESCOLA_A1]));
        $this->post($this->url(), $this->data())->assertRedirect();

        $this->post($this->url(), $this->data([
            'cpf' => '987.654.321-00',
            'full_name' => 'João Souza Fictício',
            'relationship' => 'father',
        ]))->assertSessionHas('status',
            'João Souza Fictício passou a ser o contato principal (antes: Maria da Silva Fictícia).');

        [$old, $current, $oldAudit] = $this->inMunicipality(function (): array {
            $old = StudentGuardian::query()->whereHas('guardian', fn ($query) => $query->where('cpf', '123.456.789-09'))->sole();
            $current = StudentGuardian::query()->whereHas('guardian', fn ($query) => $query->where('cpf', '987.654.321-00'))->sole();
            $oldAudit = AuditLog::query()->where('auditable_type', StudentGuardian::class)
                ->where('auditable_id', $old->id)->where('action', 'updated')->sole();

            return [$old, $current, $oldAudit];
        });
        $this->assertFalse($old->is_primary_contact);
        $this->assertTrue($current->is_primary_contact);
        $this->assertTrue($oldAudit->before['is_primary_contact']);
        $this->assertFalse($oldAudit->after['is_primary_contact']);

        $this->put($this->url().'/'.$old->id, $this->data())
            ->assertSessionHas('status',
                'Maria da Silva Fictícia passou a ser o contato principal (antes: João Souza Fictício).');

        $this->inMunicipality(function () use ($old, $current): void {
            $this->assertTrue(StudentGuardian::query()->findOrFail($old->id)->is_primary_contact);
            $this->assertFalse(StudentGuardian::query()->findOrFail($current->id)->is_primary_contact);
            $updates = AuditLog::query()->where('auditable_type', StudentGuardian::class)
                ->where('auditable_id', $current->id)->where('action', 'updated')->get();
            $this->assertCount(1, $updates);
            $this->assertTrue($updates->first()->before['is_primary_contact']);
            $this->assertFalse($updates->first()->after['is_primary_contact']);

            // O índice parcial continua fechando alterações diretas fora do controlador.
            try {
                DB::connection('tenant')->table('student_guardians')->where('id', $current->id)
                    ->update(['is_primary_contact' => true]);
                $this->fail('O índice parcial deve bloquear dois contatos principais.');
            } catch (QueryException $exception) {
                $this->assertSame('23505', $exception->errorInfo[0]);
            }
        });
    }

    public function test_professor_e_secretaria_de_outra_escola_recebem_403(): void
    {
        $this->verifyTwoFactor($this->userWithRole('school_secretary', [self::ESCOLA_A1]));
        $this->post($this->url(), $this->data())->assertRedirect();
        $linkId = $this->inMunicipality(fn (): string => StudentGuardian::query()->sole()->id);

        $this->verifyTwoFactor($this->userWithRole('teacher', [self::ESCOLA_A1]));
        $this->get($this->url().'/novo')->assertForbidden();
        $this->post($this->url(), $this->data())->assertForbidden();
        $this->get($this->url().'/'.$linkId.'/editar')->assertForbidden();
        $this->put($this->url().'/'.$linkId, $this->data())->assertForbidden();

        $this->verifyTwoFactor($this->userWithRole('school_secretary', [self::ESCOLA_A2]));
        $this->get($this->url().'/novo')->assertForbidden();
        $this->post($this->url(), $this->data())->assertForbidden();
        $this->get($this->url().'/'.$linkId.'/editar')->assertForbidden();
        $this->put($this->url().'/'.$linkId, $this->data())->assertForbidden();
        $this->assertSame(1, $this->inMunicipality(fn (): int => Guardian::query()->count()));
    }

    public function test_gestor_de_outra_rede_nao_acessa_os_responsaveis(): void
    {
        $this->verifyTwoFactor($this->userWithRole('municipal_manager', [], true));
        $this->post($this->url(), $this->data())->assertForbidden();
        $this->assertSame(0, $this->inMunicipality(fn (): int => Guardian::query()->count()));
    }

    public function test_sem_segundo_fator_nao_grava_nem_edita(): void
    {
        $user = $this->userWithRole('school_secretary', [self::ESCOLA_A1]);
        $this->actingAs($user)->post($this->url(), $this->data())->assertRedirect('/seguranca/segundo-fator/desafio');
        $this->assertSame(0, $this->inMunicipality(fn (): int => Guardian::query()->count()));

        $this->verifyTwoFactor($user);
        $this->post($this->url(), $this->data())->assertRedirect();
        $linkId = $this->inMunicipality(fn (): string => StudentGuardian::query()->sole()->id);

        $another = $this->userWithRole('school_director', [self::ESCOLA_A1]);
        $this->actingAs($another)->put($this->url().'/'.$linkId, $this->data(['full_name' => 'Não gravar']))
            ->assertRedirect('/seguranca/segundo-fator/desafio');

        $this->assertSame('Maria da Silva Fictícia', $this->inMunicipality(fn (): string => Guardian::query()->sole()->full_name));
    }

    public function test_formato_de_cpf_telefone_e_parentesco_invalidos_sao_recusados(): void
    {
        $this->verifyTwoFactor($this->userWithRole('school_secretary', [self::ESCOLA_A1]));
        $this->post($this->url(), $this->data([
            'cpf' => '1234567890', 'phone' => '123', 'relationship' => 'filho',
        ]))->assertSessionHasErrors([
            'cpf' => 'O CPF tem 11 números. Exemplo: 123.456.789-09.',
            'phone' => 'Informe um telefone com DDD e 10 ou 11 números.',
            'relationship' => 'Selecione um parentesco válido.',
        ]);

        $this->post($this->url(), $this->data(['cpf' => '123.456.789-00']))
            ->assertSessionHasErrors(['cpf' => 'CPF inválido: os dígitos verificadores não conferem. Confira com o documento.']);

        $this->post($this->url(), $this->data(['cpf' => '', 'phone' => '']))
            ->assertSessionHasErrors(['phone' => 'Informe um telefone para contato.']);

        $this->post($this->url(), $this->data(['cpf' => '12345678909', 'is_primary_contact' => '0']))
            ->assertRedirect();
        $this->assertSame('123.456.789-09', $this->inMunicipality(fn (): string => Guardian::query()->sole()->cpf));

        $this->post($this->url($this->studentSibling), $this->data([
            'cpf' => '', 'relationship' => 'other', 'is_primary_contact' => '0',
        ]))->assertRedirect();
        $this->assertSame(1, $this->inMunicipality(fn (): int => Guardian::query()->whereNull('cpf')->count()));
    }

    public function test_edicao_avisa_que_o_responsavel_compartilhado_sera_alterado_para_todos(): void
    {
        $this->verifyTwoFactor($this->userWithRole('school_secretary', [self::ESCOLA_A1]));
        $this->post($this->url(), $this->data())->assertRedirect();
        $this->post($this->url($this->studentSibling), $this->data(['is_primary_contact' => '0']))->assertRedirect();
        $linkId = $this->inMunicipality(fn (): string => StudentGuardian::query()->where('student_id', $this->studentA)->sole()->id);

        $this->get($this->url().'/'.$linkId.'/editar')
            ->assertOk()
            ->assertSee('Este responsável está ligado a 2 alunos. Nome, CPF, telefone e e-mail alterados aqui valem para todos eles.');
    }

    public function test_telefone_com_dez_digitos_aparece_formatado_na_ficha(): void
    {
        $this->verifyTwoFactor($this->userWithRole('school_secretary', [self::ESCOLA_A1]));
        $this->post($this->url(), $this->data(['phone' => '1133334444']))->assertRedirect();

        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$this->studentA)
            ->assertOk()
            ->assertSee('(11) 3333-4444');
        $this->assertSame('1133334444', $this->inMunicipality(fn (): string => Guardian::query()->sole()->phone));
    }
}
