<?php

namespace Tests\Feature\Students;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\Guardian;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentGuardian;
use App\Models\Tenant\StudentPickupAuthorization;
use App\Models\User;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class StudentPickupAuthorizationTest extends TwoMunicipalityTestCase
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
        return '/m/municipio-a/escolas/'.$school.'/alunos/'.($student ?? $this->studentA).'/pessoas-autorizadas';
    }

    private function showUrl(?string $student = null, string $school = self::ESCOLA_A1): string
    {
        return '/m/municipio-a/escolas/'.$school.'/alunos/'.($student ?? $this->studentA);
    }

    private function data(array $override = []): array
    {
        return array_replace([
            'full_name' => 'Ana Pessoa Fictícia',
            'relationship' => 'Vizinha',
            'phone' => '(11) 98765-4321',
            'document' => 'RG 12.345.678-X',
            'expires_at' => '',
            'notes' => 'Buscar somente após combinar com a escola.',
        ], $override);
    }

    public function test_cadastro_adicional_grava_sem_duplicar_responsaveis_e_mascara_documento_e_telefone_na_auditoria(): void
    {
        $this->verifyTwoFactor($this->userWithRole('school_secretary', [self::ESCOLA_A1]));
        $this->get($this->url().'/nova')->assertOk()
            ->assertSee('Adicionar pessoa autorizada')
            ->assertSee('Autorizado até')
            ->assertSee('data-guardian-phone');

        $this->post($this->url(), $this->data())
            ->assertRedirect($this->showUrl().'#quem-pode-buscar')
            ->assertSessionHas('status', 'Autorização de retirada cadastrada com sucesso.');

        [$authorization, $audit] = $this->inMunicipality(fn (): array => [
            StudentPickupAuthorization::query()->sole(),
            AuditLog::query()->where('auditable_type', StudentPickupAuthorization::class)->where('action', 'created')->sole(),
        ]);
        $this->assertSame($this->studentA, $authorization->student_id);
        $this->assertSame('Ana Pessoa Fictícia', $authorization->full_name);
        $this->assertSame('Vizinha', $authorization->relationship);
        $this->assertSame('11987654321', $authorization->phone);
        $this->assertSame('RG 12.345.678-X', $authorization->document);
        $this->assertNull($authorization->expires_at);
        $this->assertSame('Ana Pessoa Fictícia', $audit->after['full_name']);
        $this->assertSame('[REDACTED]', $audit->after['document']);
        $this->assertSame('[REDACTED]', $audit->after['phone']);
        $this->assertNull($audit->before);
        $this->assertStringNotContainsString('12.345.678-X', json_encode($audit->after));
        $this->assertStringNotContainsString('11987654321', json_encode($audit->after));
        $this->get($this->showUrl())->assertOk()
            ->assertSee('Quem pode buscar')
            ->assertSee('Outras pessoas autorizadas')
            ->assertSee('Ana Pessoa Fictícia')
            ->assertSee('1 pessoa autorizada')
            ->assertSee('(11) 98765-4321')
            ->assertSee('RG 12.345.678-X')
            ->assertSee('Editar')
            ->assertSee('Remover');
    }

    public function test_responsaveis_vinculados_ja_estao_autorizados_e_nao_viram_outra_pessoa(): void
    {
        $this->inMunicipality(function (): void {
            $guardian = Guardian::query()->create([
                'id' => (string) Str::uuid(),
                'full_name' => 'Responsável Família Fictícia',
                'phone' => '1133334444',
            ]);
            StudentGuardian::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $this->studentA,
                'guardian_id' => $guardian->id,
                'relationship' => 'mother',
                'is_legal_guardian' => true,
                'is_primary_contact' => true,
            ]);
        });

        $this->verifyTwoFactor($this->userWithRole('teacher', [self::ESCOLA_A1]));
        $this->get($this->showUrl())->assertOk()
            ->assertSee('Responsáveis (autorizados)')
            ->assertSee('Responsável Família Fictícia')
            ->assertSee('(11) 3333-4444')
            ->assertSee('1 pessoa autorizada')
            ->assertDontSee('Adicionar pessoa autorizada')
            ->assertDontSee('Remover autorização');
        $this->assertSame(0, $this->inMunicipality(fn (): int => StudentPickupAuthorization::query()->count()));
    }

    public function test_edicao_audita_antes_e_depois_e_mantem_documento_e_telefone_ocultos(): void
    {
        $this->verifyTwoFactor($this->userWithRole('school_director', [self::ESCOLA_A1]));
        $this->post($this->url(), $this->data())->assertRedirect();
        $id = $this->inMunicipality(fn (): string => StudentPickupAuthorization::query()->sole()->id);

        $this->get($this->url().'/'.$id.'/editar')->assertOk()->assertSee('Editar autorização de retirada');
        $this->put($this->url().'/'.$id, $this->data([
            'full_name' => 'Ana Fictícia Atualizada',
            'phone' => '(11) 4444-5555',
            'document' => 'CPF 123.456.789-09',
            'relationship' => 'Transporte escolar',
        ]))->assertRedirect($this->showUrl().'#quem-pode-buscar');

        [$person, $audit] = $this->inMunicipality(fn (): array => [
            StudentPickupAuthorization::query()->sole(),
            AuditLog::query()->where('auditable_type', StudentPickupAuthorization::class)->where('action', 'updated')->sole(),
        ]);
        $this->assertSame('Ana Fictícia Atualizada', $person->full_name);
        $this->assertSame('1144445555', $person->phone);
        $this->assertSame('CPF 123.456.789-09', $person->document);
        $this->assertSame('Ana Pessoa Fictícia', $audit->before['full_name']);
        $this->assertSame('Ana Fictícia Atualizada', $audit->after['full_name']);
        $this->assertSame('Vizinha', $audit->before['relationship']);
        $this->assertSame('Transporte escolar', $audit->after['relationship']);
        $this->assertSame('[REDACTED]', $audit->before['phone']);
        $this->assertSame('[REDACTED]', $audit->after['phone']);
        $this->assertSame('[REDACTED]', $audit->before['document']);
        $this->assertSame('[REDACTED]', $audit->after['document']);
        $this->assertStringNotContainsString('123.456.789-09', json_encode([$audit->before, $audit->after]));
    }

    public function test_validade_inclui_ultimo_dia_e_autorizacao_vencida_fica_riscada_e_fora_da_contagem(): void
    {
        $this->travelTo(Carbon::parse('2026-11-22 12:00:00'));
        $user = $this->userWithRole('school_secretary', [self::ESCOLA_A1]);
        $this->verifyTwoFactor($user);
        $this->post($this->url(), $this->data([
            'full_name' => 'Pessoa com prazo vencido',
            'expires_at' => '2026-11-21',
        ]))->assertRedirect();
        $this->post($this->url(), $this->data([
            'full_name' => 'Pessoa válida hoje',
            'expires_at' => '2026-11-22',
        ]))->assertRedirect();

        $this->get($this->showUrl())->assertOk()
            ->assertSee('Vencida em 21/11/2026')
            ->assertSee('retirada-vencida')
            ->assertSee('1 pessoa autorizada')
            ->assertSee('Pessoa válida hoje');
        $this->assertSame(2, $this->inMunicipality(fn (): int => StudentPickupAuthorization::query()->count()));
        $this->assertTrue($this->inMunicipality(fn (): bool => StudentPickupAuthorization::query()->where('full_name', 'Pessoa com prazo vencido')->sole()->isExpired()));
        $this->assertFalse($this->inMunicipality(fn (): bool => StudentPickupAuthorization::query()->where('full_name', 'Pessoa válida hoje')->sole()->isExpired()));

        $this->travel(1)->day();
        // O salto de um dia expira a confirmação de login do segundo fator.
        $this->verifyTwoFactor($user);
        $this->get($this->showUrl())->assertOk()
            ->assertSee('Vencida em 22/11/2026')
            ->assertSee('0 pessoas autorizadas');
        $this->travelBack();
    }

    public function test_validacao_em_portugues_recusa_campos_obrigatorios_e_telefone_sem_ddd(): void
    {
        $this->verifyTwoFactor($this->userWithRole('school_secretary', [self::ESCOLA_A1]));
        $this->post($this->url(), $this->data([
            'full_name' => ' ', 'phone' => '1234', 'expires_at' => '22/11/2026',
        ]))->assertSessionHasErrors([
            'full_name' => 'Informe o nome completo de quem pode buscar o aluno.',
            'phone' => 'Informe um telefone com DDD e 10 ou 11 números.',
            'expires_at' => 'Informe uma validade no formato dia/mês/ano.',
        ]);
        $this->post($this->url(), $this->data([
            'full_name' => 'Nome Fictício', 'phone' => '',
        ]))->assertSessionHasErrors([
            'phone' => 'Informe um telefone com DDD.',
        ]);
        $this->assertSame(0, $this->inMunicipality(fn (): int => StudentPickupAuthorization::query()->count()));
    }

    public function test_professor_so_consulta_e_outra_escola_nao_ler_nem_altera(): void
    {
        $this->verifyTwoFactor($this->userWithRole('school_secretary', [self::ESCOLA_A1]));
        $this->post($this->url(), $this->data())->assertRedirect();
        $id = $this->inMunicipality(fn (): string => StudentPickupAuthorization::query()->sole()->id);
        $reason = ['motivo' => 'Retirada não mais permitida na escola fictícia.'];

        $this->verifyTwoFactor($this->userWithRole('teacher', [self::ESCOLA_A1]));
        $this->get($this->showUrl())->assertOk()->assertSee('Ana Pessoa Fictícia')->assertDontSee('Adicionar pessoa autorizada');
        $this->get($this->url().'/nova')->assertForbidden();
        $this->post($this->url(), $this->data())->assertForbidden();
        $this->get($this->url().'/'.$id.'/editar')->assertForbidden();
        $this->put($this->url().'/'.$id, $this->data())->assertForbidden();
        $this->get($this->url().'/'.$id.'/remover')->assertForbidden();
        $this->post($this->url().'/'.$id.'/remover', $reason)->assertForbidden();

        $this->verifyTwoFactor($this->userWithRole('school_secretary', [self::ESCOLA_A2]));
        $this->get($this->showUrl())->assertForbidden();
        $this->get($this->url().'/nova')->assertForbidden();
        $this->post($this->url(), $this->data())->assertForbidden();
        $this->get($this->url().'/'.$id.'/editar')->assertForbidden();
        $this->put($this->url().'/'.$id, $this->data())->assertForbidden();
        $this->get($this->url().'/'.$id.'/remover')->assertForbidden();
        $this->post($this->url().'/'.$id.'/remover', $reason)->assertForbidden();
        $this->assertSame(1, $this->inMunicipality(fn (): int => StudentPickupAuthorization::query()->count()));
    }

    public function test_outro_municipio_e_autorizacao_de_outro_aluno_nao_vazam(): void
    {
        $this->verifyTwoFactor($this->userWithRole('school_secretary', [self::ESCOLA_A1]));
        $this->post($this->url($this->studentSibling), $this->data())->assertRedirect();
        $id = $this->inMunicipality(fn (): string => StudentPickupAuthorization::query()->sole()->id);

        $this->get($this->url().'/'.$id.'/editar')->assertNotFound();
        $this->put($this->url().'/'.$id, $this->data())->assertNotFound();
        $this->get($this->url().'/'.$id.'/remover')->assertNotFound();
        $this->post($this->url().'/'.$id.'/remover', ['motivo' => 'Retirada deste outro aluno não autorizada.'])->assertNotFound();
        $this->get($this->showUrl())->assertOk()->assertDontSee('Ana Pessoa Fictícia');
        $this->get($this->showUrl($this->studentSibling))->assertOk()->assertSee('Ana Pessoa Fictícia');

        $this->verifyTwoFactor($this->userWithRole('municipal_manager', [], true));
        $this->get($this->showUrl())->assertForbidden();
        $this->post($this->url(), $this->data())->assertForbidden();
        $this->assertSame(1, $this->inMunicipality(fn (): int => StudentPickupAuthorization::query()->count()));
    }

    public function test_sem_segundo_fator_confirmado_nao_grava_nem_edita(): void
    {
        $user = $this->userWithRole('school_secretary', [self::ESCOLA_A1]);
        $this->actingAs($user)->post($this->url(), $this->data())->assertRedirect('/seguranca/segundo-fator/desafio');
        $this->assertSame(0, $this->inMunicipality(fn (): int => StudentPickupAuthorization::query()->count()));

        $this->verifyTwoFactor($user);
        $this->post($this->url(), $this->data())->assertRedirect();
        $id = $this->inMunicipality(fn (): string => StudentPickupAuthorization::query()->sole()->id);

        $other = $this->userWithRole('school_director', [self::ESCOLA_A1]);
        $this->actingAs($other)->put($this->url().'/'.$id, $this->data(['full_name' => 'Pessoa não alterada']))
            ->assertRedirect('/seguranca/segundo-fator/desafio');
        $this->assertSame('Ana Pessoa Fictícia', $this->inMunicipality(fn (): string => StudentPickupAuthorization::query()->sole()->full_name));
    }

    public function test_remocao_exige_motivo_e_segundo_fator_recente_e_preserva_responsavel(): void
    {
        $this->verifyTwoFactor($this->userWithRole('school_secretary', [self::ESCOLA_A1]));
        $this->post($this->url(), $this->data())->assertRedirect();
        $id = $this->inMunicipality(fn (): string => StudentPickupAuthorization::query()->sole()->id);
        $this->inMunicipality(function (): void {
            $guardian = Guardian::query()->create([
                'id' => (string) Str::uuid(),
                'full_name' => 'Pessoa da Família Fictícia',
                'phone' => '1133334444',
            ]);
            StudentGuardian::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $this->studentA,
                'guardian_id' => $guardian->id,
                'relationship' => 'mother',
                'is_legal_guardian' => true,
                'is_primary_contact' => true,
            ]);
        });
        $postUrl = $this->url().'/'.$id.'/remover';

        $this->get($postUrl)->assertOk()->assertSee('Ana Pessoa Fictícia')->assertSee('Vizinha');
        $this->post($postUrl, ['motivo' => ''])->assertSessionHasErrors(['motivo' => 'Informe o motivo da remoção da autorização.']);
        $this->post($postUrl, ['motivo' => '   '])->assertSessionHasErrors(['motivo' => 'Informe o motivo da remoção da autorização.']);
        $this->post($postUrl, ['motivo' => 'Curto1234'])->assertSessionHasErrors(['motivo' => 'O motivo deve ter pelo menos 10 caracteres.']);
        $this->post($postUrl, ['motivo' => str_repeat('x', 501)])->assertSessionHasErrors(['motivo' => 'O motivo deve ter no máximo 500 caracteres.']);
        $this->assertSame(1, $this->inMunicipality(fn (): int => StudentPickupAuthorization::query()->count()));

        $motivo = 'A escola confirmou a suspensão da autorização fictícia.';
        $this->travel(16)->minutes();
        $this->from($postUrl)->post($postUrl, ['motivo' => $motivo])
            ->assertRedirect('/seguranca/segundo-fator/desafio');
        $this->assertSame(1, $this->inMunicipality(fn (): int => StudentPickupAuthorization::query()->count()));

        $this->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp()),
        ])->assertRedirect($postUrl);
        $this->get($postUrl)->assertOk()->assertSee($motivo);
        $this->post($postUrl, ['motivo' => $motivo])
            ->assertRedirect($this->showUrl().'#quem-pode-buscar')
            ->assertSessionHas('status', 'Autorização de retirada removida. A pessoa não consta mais na lista de autorizados.');

        [$remaining, $audit] = $this->inMunicipality(fn (): array => [
            StudentPickupAuthorization::query()->count(),
            AuditLog::query()->where('auditable_type', StudentPickupAuthorization::class)->where('action', 'deleted')->sole(),
        ]);
        $this->assertSame(0, $remaining);
        $this->assertSame(1, $this->inMunicipality(fn (): int => Guardian::query()->count()));
        $this->assertSame(1, $this->inMunicipality(fn (): int => StudentGuardian::query()->count()));
        $this->assertSame($id, $audit->auditable_id);
        $this->assertSame('Ana Pessoa Fictícia', $audit->before['full_name']);
        $this->assertSame('[REDACTED]', $audit->before['phone']);
        $this->assertSame('[REDACTED]', $audit->before['document']);
        $this->assertSame($motivo, $audit->after['motivo']);
        $this->assertStringNotContainsString('11987654321', json_encode([$audit->before, $audit->after]));
        $this->assertStringNotContainsString('12.345.678-X', json_encode([$audit->before, $audit->after]));
        $this->travelBack();
    }
}
