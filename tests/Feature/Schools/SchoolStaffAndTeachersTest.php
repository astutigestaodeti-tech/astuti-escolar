<?php

namespace Tests\Feature\Schools;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\ClassTeacherAssignment;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\StaffMember;
use App\Models\User;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class SchoolStaffAndTeachersTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private string $classId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classId = $this->inMunicipality(function (): string {
            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(),
                'year' => 2027,
                'start_date' => '2027-02-01',
                'end_date' => '2027-12-18',
                'status' => 'active',
            ]);

            return SchoolClass::query()->create([
                'id' => (string) Str::uuid(),
                'school_id' => self::ESCOLA_A1,
                'school_year_id' => $year->id,
                'name' => '5º Ano B',
                'stage' => 'Ensino Fundamental',
                'modality' => 'Regular',
                'shift' => 'Manhã',
                'capacity' => 25,
                'status' => 'active',
            ])->id;
        });
    }

    private function inMunicipality(callable $callback): mixed
    {
        return app(TenantContext::class)->runFor($this->municipioA, $callback);
    }

    private function userWithRole(string $role, array $schoolIds = []): User
    {
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schoolIds);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();

        return $user;
    }

    private function verifyTwoFactor(User $user): void
    {
        $this->actingAs($user)
            ->post('/seguranca/segundo-fator/desafio', ['code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp())])
            ->assertRedirect();
    }

    private function staffUrl(string $suffix = '', string $school = self::ESCOLA_A1): string
    {
        return '/m/municipio-a/escolas/'.$school.'/servidores'.$suffix;
    }

    private function classUrl(string $suffix = '', string $school = self::ESCOLA_A1): string
    {
        return '/m/municipio-a/escolas/'.$school.'/turmas/'.$this->classId.$suffix;
    }

    private function staffData(array $override = []): array
    {
        return array_replace([
            'full_name' => 'Ana Júlia de Oliveira',
            'registration_number' => 'FUNC-123',
            'role' => 'teacher',
            'institutional_email' => 'ana.prof@escola.invalid',
            'phone' => '(11) 98765-4321',
            'status' => 'active',
        ], $override);
    }

    private function createStaff(string $name = 'Ana Júlia de Oliveira', string $school = self::ESCOLA_A1): StaffMember
    {
        return $this->inMunicipality(fn (): StaffMember => StaffMember::query()->create([
            'id' => (string) Str::uuid(),
            'school_id' => $school,
            'full_name' => $name,
            'registration_number' => null,
            'role' => 'teacher',
            'institutional_email' => null,
            'phone' => null,
            'status' => 'active',
        ]));
    }

    private function assignmentData(StaffMember $member, array $override = []): array
    {
        return array_replace([
            'staff_member_id' => $member->id,
            'subject' => 'Língua Portuguesa',
            'starts_on' => '2027-02-01',
            'is_main' => '1',
        ], $override);
    }

    public function test_cadastro_edicao_busca_sem_acento_e_auditoria_mascara_contatos(): void
    {
        $this->verifyTwoFactor($this->userWithRole('school_secretary', [self::ESCOLA_A1]));

        $this->get($this->staffUrl())->assertOk()->assertSee('Novo servidor');
        $this->get($this->staffUrl('/novo'))->assertOk()->assertSee('Matrícula funcional');
        $this->post($this->staffUrl(), $this->staffData())->assertRedirect($this->staffUrl());

        $member = $this->inMunicipality(fn (): StaffMember => StaffMember::query()->sole());
        $this->assertSame('Ana Júlia de Oliveira', $member->full_name);
        $this->assertSame('11987654321', $member->phone);
        $this->get($this->staffUrl('?busca=ana%20julia'))->assertOk()
            ->assertSee('Ana Júlia de Oliveira')->assertSee('(11) 98765-4321');
        $this->get($this->staffUrl('?busca=inexistente'))->assertOk()->assertDontSee('Ana Júlia de Oliveira');

        $this->put($this->staffUrl('/'.$member->id), $this->staffData([
            'full_name' => 'Ana Júlia Oliveira',
            'phone' => '11999998888',
            'institutional_email' => 'ana.nova@escola.invalid',
        ]))->assertRedirect($this->staffUrl());

        $this->inMunicipality(function (): void {
            $created = AuditLog::query()->where('auditable_type', StaffMember::class)->where('action', 'created')->sole();
            $updated = AuditLog::query()->where('auditable_type', StaffMember::class)->where('action', 'updated')->sole();

            $this->assertSame('[REDACTED]', $created->after['phone']);
            $this->assertSame('[REDACTED]', $created->after['institutional_email']);
            $this->assertSame('Ana Júlia de Oliveira', $updated->before['full_name']);
            $this->assertSame('Ana Júlia Oliveira', $updated->after['full_name']);
            $this->assertSame('[REDACTED]', $updated->before['phone']);
            $this->assertSame('[REDACTED]', $updated->after['phone']);
            $this->assertSame('[REDACTED]', $updated->before['institutional_email']);
            $this->assertSame('[REDACTED]', $updated->after['institutional_email']);
            $this->assertStringNotContainsString('11999998888', $updated->toJson());
            $this->assertStringNotContainsString('ana.nova@escola.invalid', $updated->toJson());
        });
    }

    public function test_professor_principal_unico_encerra_e_relatorio_do_mes_mostra_professor_vigente(): void
    {
        $this->verifyTwoFactor($this->userWithRole('school_director', [self::ESCOLA_A1]));
        $ana = $this->createStaff();
        $bruno = $this->createStaff('Bruno Ribeiro');

        $this->post($this->classUrl('/professores'), $this->assignmentData($ana))
            ->assertRedirect($this->classUrl());
        $this->get($this->classUrl())->assertOk()->assertSee('Professores da turma')->assertSee('Ana Júlia de Oliveira');
        $this->get($this->classUrl('/frequencia-mensal?mes=2027-02'))
            ->assertOk()->assertSee('Ana Júlia de Oliveira');

        $this->post($this->classUrl('/professores'), $this->assignmentData($bruno))
            ->assertSessionHasErrors(['is_main' => 'Esta turma já tem professor principal em aberto. Encerre a atribuição anterior primeiro.']);

        $first = $this->inMunicipality(fn (): ClassTeacherAssignment => ClassTeacherAssignment::query()->sole());
        $this->post($this->classUrl('/professores/'.$first->id.'/encerrar'), ['ends_on' => '2027-03-15'])
            ->assertRedirect($this->classUrl());
        $this->post($this->classUrl('/professores'), $this->assignmentData($bruno, ['starts_on' => '2027-03-16']))
            ->assertRedirect($this->classUrl());

        $this->get($this->classUrl('/frequencia-mensal?mes=2027-03'))
            ->assertOk()->assertSee('Bruno Ribeiro');
        $this->get($this->classUrl('/frequencia-mensal?mes=2027-04'))
            ->assertOk()->assertSee('Bruno Ribeiro');

        $this->inMunicipality(function (): void {
            $created = AuditLog::query()->where('auditable_type', ClassTeacherAssignment::class)->where('action', 'created')->get();
            $ended = AuditLog::query()->where('auditable_type', ClassTeacherAssignment::class)->where('action', 'updated')->sole();
            $this->assertCount(2, $created);
            $this->assertNull($ended->before['ends_on']);
            $this->assertNotNull($ended->after['ends_on']);
            $this->assertSame(2, ClassTeacherAssignment::query()->count());
        });
    }

    public function test_indice_parcial_no_banco_impede_dois_principais_em_aberto(): void
    {
        $ana = $this->createStaff();
        $bruno = $this->createStaff('Bruno Ribeiro');

        $this->inMunicipality(function () use ($ana, $bruno): void {
            ClassTeacherAssignment::query()->create([
                'id' => (string) Str::uuid(),
                'school_class_id' => $this->classId,
                'staff_member_id' => $ana->id,
                'subject' => null,
                'is_main' => true,
                'starts_on' => '2027-02-01',
            ]);
            try {
                DB::connection('tenant')->table('class_teacher_assignments')->insert([
                    'id' => (string) Str::uuid(),
                    'school_class_id' => $this->classId,
                    'staff_member_id' => $bruno->id,
                    'is_main' => true,
                    'starts_on' => '2027-02-02',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->fail('Deveria impedir duas atribuições principais abertas.');
            } catch (QueryException $exception) {
                $this->assertSame('23505', $exception->errorInfo[0]);
            }
        });
    }

    public function test_professor_somente_consulta_e_outra_escola_nao_alcanca(): void
    {
        $ana = $this->createStaff();
        $this->verifyTwoFactor($this->userWithRole('teacher', [self::ESCOLA_A1]));

        // Professor vê o colega e o e-mail institucional, mas não o telefone.
        $this->get($this->staffUrl())->assertOk()->assertSee('Ana Júlia de Oliveira')->assertDontSee('Novo servidor')
            ->assertDontSee('(11) 98765-4321')->assertDontSee('tel:+55', false);
        $this->get($this->classUrl())->assertOk()->assertSee('Professores da turma')->assertDontSee('Atribuir professor');
        $this->get($this->staffUrl('/novo'))->assertForbidden();
        $this->post($this->staffUrl(), $this->staffData())->assertForbidden();
        $this->put($this->staffUrl('/'.$ana->id), $this->staffData())->assertForbidden();
        $this->post($this->classUrl('/professores'), $this->assignmentData($ana))->assertForbidden();

        $this->verifyTwoFactor($this->userWithRole('school_secretary', [self::ESCOLA_A2]));
        $this->get($this->staffUrl())->assertForbidden();
        $this->get($this->classUrl())->assertForbidden();
        $this->post($this->staffUrl(), $this->staffData())->assertForbidden();
        $this->post($this->classUrl('/professores'), $this->assignmentData($ana))->assertForbidden();
        $this->get($this->staffUrl('', self::ESCOLA_A2))->assertOk()->assertDontSee('Ana Júlia de Oliveira');
    }

    public function test_sem_segundo_fator_nao_grava_servidor_nem_atribuicao(): void
    {
        $ana = $this->createStaff();
        $user = $this->userWithRole('school_secretary', [self::ESCOLA_A1]);

        $this->actingAs($user)->post($this->staffUrl(), $this->staffData())
            ->assertRedirect('/seguranca/segundo-fator/desafio');
        $this->post($this->classUrl('/professores'), $this->assignmentData($ana))
            ->assertRedirect('/seguranca/segundo-fator/desafio');

        $this->assertSame(1, $this->inMunicipality(fn (): int => StaffMember::query()->count()));
        $this->assertSame(0, $this->inMunicipality(fn (): int => ClassTeacherAssignment::query()->count()));
    }

    public function test_arquivamento_exige_motivo_e_segundo_fator_recente_sem_apagar_historico(): void
    {
        $ana = $this->createStaff();
        $user = $this->userWithRole('school_director', [self::ESCOLA_A1]);
        $this->verifyTwoFactor($user);

        $this->get($this->staffUrl('/'.$ana->id.'/arquivar'))->assertOk()->assertSee('Arquivar servidor');
        $this->delete($this->staffUrl('/'.$ana->id), ['motivo' => 'curto'])
            ->assertSessionHasErrors('motivo');

        $this->delete($this->staffUrl('/'.$ana->id), ['motivo' => 'Desligamento registrado pela gestão.'])
            ->assertRedirect($this->staffUrl());
        $this->inMunicipality(function (): void {
            $this->assertSame(0, StaffMember::query()->count());
            $this->assertSame(1, StaffMember::withTrashed()->count());
            $deleted = AuditLog::query()->where('auditable_type', StaffMember::class)->where('action', 'deleted')->sole();
            $this->assertSame('Desligamento registrado pela gestão.', $deleted->after['deletion_reason']);
        });
    }

    public function test_nunca_arquiva_professor_com_atribuicao_aberta_nem_muda_cargo_sem_encerrar(): void
    {
        $this->verifyTwoFactor($this->userWithRole('municipal_manager'));
        $ana = $this->createStaff();
        $this->post($this->classUrl('/professores'), $this->assignmentData($ana))->assertRedirect();

        $this->put($this->staffUrl('/'.$ana->id), $this->staffData(['role' => 'assistant']))
            ->assertSessionHasErrors('role');
        $this->delete($this->staffUrl('/'.$ana->id), ['motivo' => 'Desligamento registrado pela direção.'])
            ->assertSessionHasErrors('motivo');

        $this->assertSame(1, $this->inMunicipality(fn (): int => StaffMember::query()->count()));
    }
}
