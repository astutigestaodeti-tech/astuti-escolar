<?php

namespace Tests\Feature\Schools;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\SchoolClass;
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

class SchoolClassWriteTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private string $yearId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->yearId = $this->inMunicipality(function (): string {
            return SchoolYear::query()->create([
                'id' => (string) Str::uuid(),
                'year' => 2027,
                'start_date' => '2027-02-01',
                'end_date' => '2027-12-18',
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

    private function url(string $suffix = '', string $schoolId = self::ESCOLA_A1): string
    {
        return '/m/municipio-a/escolas/'.$schoolId.'/turmas'.$suffix;
    }

    private function data(array $override = []): array
    {
        return array_replace([
            'name' => '3º Ano A',
            'stage' => 'Ensino Fundamental',
            'modality' => 'Regular',
            'shift' => 'Manhã',
            'capacity' => '30',
            'school_year_id' => $this->yearId,
        ], $override);
    }

    public function test_secretaria_cria_e_edita_turma_com_auditoria_antes_e_depois(): void
    {
        $this->verifyTwoFactor($this->userWithRole('school_secretary', [self::ESCOLA_A1]));

        $this->get($this->url())->assertOk()->assertSee('Nova turma');
        $this->get($this->url('/nova'))->assertOk()->assertSee('Nome ou código')->assertSee('Ano letivo');

        $this->post($this->url(), $this->data(['name' => '  3º  Ano A  ']))
            ->assertRedirect($this->url());

        [$class, $created] = $this->inMunicipality(function (): array {
            $class = SchoolClass::query()->sole();
            $log = AuditLog::query()->where('auditable_type', SchoolClass::class)->where('action', 'created')->sole();

            return [$class, $log];
        });

        $this->assertSame('3º Ano A', $class->name);
        $this->assertSame(self::ESCOLA_A1, $class->school_id);
        $this->assertSame('active', $class->status);
        $this->assertSame(30, (int) $class->capacity);
        $this->assertNull($created->before);
        $this->assertSame('3º Ano A', $created->after['name']);
        $this->assertSame($this->yearId, $created->after['school_year_id']);
        $this->get($this->url())->assertSee('Editar')->assertSee('Turma cadastrada');

        $this->get($this->url('/'.$class->id.'/editar'))
            ->assertOk()->assertSee('name="versao"', false)->assertSee('value="3º Ano A"', false);

        $versao = $this->inMunicipality(fn (): string => DB::connection('tenant')
            ->table('school_classes')->where('id', $class->id)->selectRaw('xmin::text AS token')->sole()->token);
        $this->put($this->url('/'.$class->id), $this->data([
            'name' => '3º Ano B',
            'stage' => 'Ensino Fundamental - Anos Iniciais',
            'shift' => 'Tarde',
            'capacity' => '',
            'versao' => $versao,
        ]))->assertRedirect($this->url());

        [$edited, $updated] = $this->inMunicipality(function () use ($class): array {
            return [
                SchoolClass::query()->findOrFail($class->id),
                AuditLog::query()->where('auditable_type', SchoolClass::class)->where('action', 'updated')->sole(),
            ];
        });

        $this->assertSame('3º Ano B', $edited->name);
        $this->assertSame('Tarde', $edited->shift);
        $this->assertNull($edited->capacity);
        $this->assertSame('3º Ano A', $updated->before['name']);
        $this->assertSame('3º Ano B', $updated->after['name']);
        $this->assertSame('Manhã', $updated->before['shift']);
        $this->assertSame('Tarde', $updated->after['shift']);
        $this->assertSame(30, (int) $updated->before['capacity']);
        $this->assertNull($updated->after['capacity']);
    }

    public function test_nome_duplicado_e_bloqueado_na_escola_e_ano_inclusive_no_banco(): void
    {
        $this->verifyTwoFactor($this->userWithRole('municipal_manager'));

        $this->post($this->url(), $this->data())->assertRedirect();
        $this->from($this->url('/nova'))->post($this->url(), $this->data(['name' => '  3º ano a  ']))
            ->assertRedirect($this->url('/nova'))
            ->assertSessionHasErrors(['name' => 'Já existe uma turma ativa com este nome nesta escola e ano letivo.']);

        $this->assertSame(1, $this->inMunicipality(fn (): int => SchoolClass::query()->count()));

        $this->inMunicipality(function (): void {
            $first = SchoolClass::query()->sole();

            try {
                DB::connection('tenant')->table('school_classes')->insert([
                    'id' => (string) Str::uuid(),
                    'school_id' => self::ESCOLA_A1,
                    'school_year_id' => $this->yearId,
                    'name' => '3º ANO A',
                    'stage' => 'Ensino Fundamental',
                    'modality' => 'Regular',
                    'shift' => 'Manhã',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->fail('O índice único deve rejeitar o nome repetido.');
            } catch (QueryException $exception) {
                $this->assertSame('23505', $exception->errorInfo[0]);
            }

            $this->assertSame('3º Ano A', $first->name);
        });

        // Outros anos e outras escolas admitem o mesmo nome.
        $otherYear = $this->inMunicipality(fn (): string => SchoolYear::query()->create([
            'id' => (string) Str::uuid(),
            'year' => 2028,
            'start_date' => '2028-02-01',
            'end_date' => '2028-12-18',
            'status' => 'planned',
        ])->id);
        $this->post($this->url(), $this->data(['school_year_id' => $otherYear]))->assertRedirect();
        $this->post($this->url('', self::ESCOLA_A2), $this->data())->assertRedirect();
        $this->assertSame(3, $this->inMunicipality(fn (): int => SchoolClass::query()->count()));

        $otherYearClass = $this->inMunicipality(fn (): SchoolClass => SchoolClass::query()
            ->where('school_id', self::ESCOLA_A1)->where('school_year_id', $otherYear)->sole());
        $otherVersion = $this->inMunicipality(fn (): string => DB::connection('tenant')
            ->table('school_classes')->where('id', $otherYearClass->id)->selectRaw('xmin::text AS token')->sole()->token);
        $this->put($this->url('/'.$otherYearClass->id), $this->data([
            'name' => '3º ANO A',
            'school_year_id' => $this->yearId,
            'versao' => $otherVersion,
        ]))->assertSessionHasErrors(['name' => 'Já existe uma turma ativa com este nome nesta escola e ano letivo.']);
    }

    public function test_diretor_e_gestor_podem_gravar_e_professor_nao_pode(): void
    {
        $this->verifyTwoFactor($this->userWithRole('school_director', [self::ESCOLA_A1]));
        $this->post($this->url(), $this->data(['name' => 'Turma da Direção']))->assertRedirect();

        $this->verifyTwoFactor($this->userWithRole('municipal_manager'));
        $this->post($this->url('', self::ESCOLA_A2), $this->data(['name' => 'Turma da Gestão']))->assertRedirect();

        $teacher = $this->userWithRole('teacher', [self::ESCOLA_A1]);
        $this->verifyTwoFactor($teacher);
        $classId = $this->inMunicipality(fn (): string => SchoolClass::query()->where('school_id', self::ESCOLA_A1)->sole()->id);
        $this->get($this->url())->assertOk()->assertDontSee('Nova turma')->assertDontSee('Editar</a>', false);
        $this->get($this->url('/nova'))->assertForbidden();
        $this->get($this->url('/'.$classId.'/editar'))->assertForbidden();
        $this->post($this->url(), $this->data())->assertForbidden();
        $this->put($this->url('/'.$classId), $this->data(['versao' => '0']))->assertForbidden();

        $this->assertSame(2, $this->inMunicipality(fn (): int => SchoolClass::query()->count()));
    }

    public function test_secretaria_de_outra_escola_recebe_403_e_nao_altera_turma(): void
    {
        $this->verifyTwoFactor($this->userWithRole('school_secretary', [self::ESCOLA_A1]));
        $this->post($this->url(), $this->data())->assertRedirect();
        $id = $this->inMunicipality(fn (): string => SchoolClass::query()->sole()->id);

        $this->verifyTwoFactor($this->userWithRole('school_secretary', [self::ESCOLA_A2]));
        $this->get($this->url())->assertForbidden();
        $this->get($this->url('/nova'))->assertForbidden();
        $this->get($this->url('/'.$id.'/editar'))->assertForbidden();
        $this->post($this->url(), $this->data())->assertForbidden();
        $this->put($this->url('/'.$id), $this->data(['name' => 'Troca indevida', 'versao' => '0']))->assertForbidden();

        $this->get($this->url('', self::ESCOLA_A2))->assertOk()->assertDontSee('3º Ano A');
        $this->assertSame('3º Ano A', $this->inMunicipality(fn (): string => SchoolClass::query()->sole()->name));
    }

    public function test_sem_segundo_fator_confirmado_nao_grava(): void
    {
        $user = $this->userWithRole('school_secretary', [self::ESCOLA_A1]);
        $this->actingAs($user)->post($this->url(), $this->data())
            ->assertRedirect('/seguranca/segundo-fator/desafio');
        $this->get($this->url('/nova'))->assertRedirect('/seguranca/segundo-fator/desafio');
        $this->assertSame(0, $this->inMunicipality(fn (): int => SchoolClass::query()->count()));

        $this->verifyTwoFactor($user);
        $this->post($this->url(), $this->data())->assertRedirect();
        $this->assertSame(1, $this->inMunicipality(fn (): int => SchoolClass::query()->count()));
    }

    public function test_validacao_em_portugues_e_ano_encerrado_recusado(): void
    {
        $this->verifyTwoFactor($this->userWithRole('school_secretary', [self::ESCOLA_A1]));

        $this->post($this->url(), $this->data(['name' => '  ', 'stage' => ' ', 'capacity' => 'abc']))
            ->assertSessionHasErrors([
                'name' => 'Informe o nome ou código da turma.',
                'stage' => 'Informe a etapa de ensino.',
                'capacity' => 'Informe a quantidade de vagas em números inteiros.',
            ]);
        $this->post($this->url(), $this->data(['capacity' => 0]))
            ->assertSessionHasErrors(['capacity' => 'Informe um número de vagas entre 1 e 32767.']);

        $closedYear = $this->inMunicipality(fn (): string => SchoolYear::query()->create([
            'id' => (string) Str::uuid(),
            'year' => 2026,
            'start_date' => '2026-02-01',
            'end_date' => '2026-12-18',
            'status' => 'completed',
        ])->id);

        $this->post($this->url(), $this->data(['school_year_id' => $closedYear]))
            ->assertSessionHasErrors(['school_year_id' => 'Escolha um ano letivo em andamento ou planejado.']);

        $this->assertSame(0, $this->inMunicipality(fn (): int => SchoolClass::query()->count()));
    }

    public function test_edicao_recusa_versao_antiga_sem_sobrescrever(): void
    {
        $this->verifyTwoFactor($this->userWithRole('school_director', [self::ESCOLA_A1]));
        $this->post($this->url(), $this->data())->assertRedirect();
        $id = $this->inMunicipality(fn (): string => SchoolClass::query()->sole()->id);

        $this->put($this->url('/'.$id), $this->data(['name' => 'Troca de nome', 'versao' => '0']))
            ->assertSessionHasErrors(['versao' => 'Esta turma foi alterada por outra pessoa. Abra o formulário novamente.']);

        $this->assertSame('3º Ano A', $this->inMunicipality(fn (): string => SchoolClass::query()->sole()->name));
    }

    public function test_ano_de_turma_com_matriculas_nao_pode_ser_trocado(): void
    {
        $this->verifyTwoFactor($this->userWithRole('school_director', [self::ESCOLA_A1]));
        $this->post($this->url(), $this->data())->assertRedirect();
        $classId = $this->inMunicipality(fn (): string => SchoolClass::query()->sole()->id);

        $this->inMunicipality(function () use ($classId): void {
            $student = Student::query()->create([
                'id' => (string) Str::uuid(),
                'full_name' => 'Aluna Fictícia de Teste',
            ]);
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $student->id,
                'school_id' => self::ESCOLA_A1,
                'school_year_id' => $this->yearId,
                'school_class_id' => $classId,
                'status' => 'active',
            ]);
        });

        $otherYear = $this->inMunicipality(fn (): string => SchoolYear::query()->create([
            'id' => (string) Str::uuid(),
            'year' => 2028,
            'start_date' => '2028-02-01',
            'end_date' => '2028-12-18',
            'status' => 'planned',
        ])->id);
        $version = $this->inMunicipality(fn (): string => DB::connection('tenant')
            ->table('school_classes')->where('id', $classId)->selectRaw('xmin::text AS token')->sole()->token);

        $this->put($this->url('/'.$classId), $this->data([
            'school_year_id' => $otherYear,
            'versao' => $version,
        ]))->assertSessionHasErrors([
            'school_year_id' => 'Esta turma já tem matrículas vinculadas. O ano letivo não pode ser alterado.',
        ]);

        $this->assertSame($this->yearId, $this->inMunicipality(fn (): string => SchoolClass::query()->sole()->school_year_id));
    }
}
