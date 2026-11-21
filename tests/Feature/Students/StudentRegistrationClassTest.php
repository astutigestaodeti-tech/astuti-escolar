<?php

namespace Tests\Feature\Students;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\User;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class StudentRegistrationClassTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private string $yearId;

    private string $classA1;

    private string $classA1Other;

    private string $classA2;

    protected function setUp(): void
    {
        parent::setUp();

        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(),
                'year' => 2027,
                'start_date' => '2027-02-01',
                'end_date' => '2027-12-18',
                'status' => 'active',
            ]);
            $this->yearId = $year->id;
            $this->classA1 = $this->turma(self::ESCOLA_A1, '1º Ano A');
            $this->classA1Other = $this->turma(self::ESCOLA_A1, '1º Ano B');
            $this->classA2 = $this->turma(self::ESCOLA_A2, '2º Ano A');
        });
    }

    private function turma(string $schoolId, string $name): string
    {
        return SchoolClass::query()->create([
            'id' => (string) Str::uuid(),
            'school_id' => $schoolId,
            'school_year_id' => $this->yearId,
            'name' => $name,
            'stage' => 'Ensino Fundamental',
            'modality' => 'Regular',
            'shift' => 'Manhã',
            'status' => 'active',
        ])->id;
    }

    private function entrar(string $role = 'school_secretary', array $schools = [self::ESCOLA_A1]): User
    {
        $user = $this->criarUsuario('ra-turma-'.Str::random(8).'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schools);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();

        $this->actingAs($user)
            ->post('/seguranca/segundo-fator/desafio', ['code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp())])
            ->assertRedirect();

        return $user;
    }

    private function url(string $suffix = ''): string
    {
        return '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos'.$suffix;
    }

    private function dados(array $extra = []): array
    {
        return array_replace([
            'full_name' => 'Camila Fictícia de Oliveira',
            'birth_date' => '2018-04-05',
            'ra' => '123456789-0/sp',
            'school_year_id' => $this->yearId,
            'school_class_id' => $this->classA1,
            'started_at' => '2027-02-03',
        ], $extra);
    }

    public function test_secretaria_cadastra_ra_e_turma_da_mesma_escola_com_auditoria(): void
    {
        $user = $this->entrar();

        $this->get($this->url('/novo'))
            ->assertOk()
            ->assertSee('RA do aluno')
            ->assertSee('Turma da matrícula');

        $this->post($this->url(), $this->dados())->assertRedirect();

        [$student, $enrollment, $logs] = app(TenantContext::class)->runFor($this->municipioA, function (): array {
            $student = Student::query()->firstOrFail();
            $enrollment = StudentEnrollment::query()->where('student_id', $student->id)->firstOrFail();
            $logs = AuditLog::query()->whereIn('auditable_type', [Student::class, StudentEnrollment::class])->get();

            return [$student, $enrollment, $logs];
        });

        $this->assertSame('123456789-0/SP', $student->ra);
        $this->assertSame($this->classA1, $enrollment->school_class_id);
        $this->assertCount(2, $logs);
        $this->assertTrue($logs->every(fn (AuditLog $log): bool => $log->user_id === $user->id));
        $this->assertTrue($logs->contains(fn (AuditLog $log): bool => ($log->after['ra'] ?? null) === '123456789-0/SP'));
        $this->assertTrue($logs->contains(fn (AuditLog $log): bool => ($log->after['school_class_id'] ?? null) === $this->classA1));

        $this->get($this->url('/'.$student->id))
            ->assertOk()
            ->assertSee('123456789-0/SP')
            ->assertSee('1º Ano A');
    }

    public function test_ra_duplicado_e_turma_de_outra_escola_sao_recusados_sem_gravacao_parcial(): void
    {
        $this->entrar();
        $this->post($this->url(), $this->dados())->assertRedirect();

        $this->post($this->url(), $this->dados([
            'full_name' => 'Outra Aluna Fictícia',
            'ra' => '123456789-0/SP',
        ]))->assertSessionHasErrors('ra');

        $this->post($this->url(), $this->dados([
            'full_name' => 'Terceira Aluna Fictícia',
            'ra' => '987654321-0/SP',
            'school_class_id' => $this->classA2,
        ]))->assertSessionHasErrors('school_class_id');

        $this->assertSame(1, app(TenantContext::class)->runFor($this->municipioA, fn (): int => Student::query()->count()));
    }

    public function test_ano_letivo_diferente_e_ra_repetido_no_banco_sao_recusados(): void
    {
        $this->entrar();
        $this->post($this->url(), $this->dados())->assertRedirect();

        $yearOther = app(TenantContext::class)->runFor($this->municipioA, function (): SchoolYear {
            return SchoolYear::query()->create([
                'id' => (string) Str::uuid(),
                'year' => 2028,
                'start_date' => '2028-02-01',
                'end_date' => '2028-12-18',
                'status' => 'planned',
            ]);
        });

        $otherClass = app(TenantContext::class)->runFor($this->municipioA, function () use ($yearOther): string {
            return SchoolClass::query()->create([
                'id' => (string) Str::uuid(),
                'school_id' => self::ESCOLA_A1,
                'school_year_id' => $yearOther->id,
                'name' => '1º Ano 2028',
                'stage' => 'Ensino Fundamental',
                'modality' => 'Regular',
                'shift' => 'Manhã',
                'status' => 'planned',
            ])->id;
        });

        $this->post($this->url(), $this->dados([
            'full_name' => 'Aluna Fictícia Outro Ano',
            'ra' => '333333333-0/SP',
            'school_class_id' => $otherClass,
        ]))->assertSessionHasErrors('school_class_id');

        try {
            app(TenantContext::class)->runFor($this->municipioA, fn () => Student::query()->create([
                'id' => (string) Str::uuid(),
                'full_name' => 'Pessoa Fictícia Com RA Repetido',
                'ra' => '123456789-0/SP',
            ]));
            $this->fail('O banco precisa impedir RA duplicado.');
        } catch (QueryException $exception) {
            $this->assertSame('23505', $exception->getCode());
        }

        $this->assertSame(1, app(TenantContext::class)->runFor($this->municipioA, fn (): int => Student::query()->count()));
    }

    public function test_banco_recusa_turma_de_outra_escola_mesmo_sem_passar_pelo_formulario(): void
    {
        $this->entrar();

        $student = app(TenantContext::class)->runFor($this->municipioA, fn (): Student => Student::query()->create([
            'id' => (string) Str::uuid(),
            'full_name' => 'Aluno Fictício Integridade',
        ]));

        try {
            app(TenantContext::class)->runFor($this->municipioA, fn () => StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $student->id,
                'school_id' => self::ESCOLA_A1,
                'school_year_id' => $this->yearId,
                'school_class_id' => $this->classA2,
                'status' => 'active',
            ]));
            $this->fail('O banco precisa impedir turma de outra escola.');
        } catch (QueryException $exception) {
            $this->assertSame('23503', $exception->getCode());
        }

        $this->assertSame(0, app(TenantContext::class)->runFor($this->municipioA, fn (): int => StudentEnrollment::query()->count()));
    }

    public function test_diretor_edita_ra_e_turma_com_versoes_e_auditoria(): void
    {
        $this->entrar('school_director');
        $this->post($this->url(), $this->dados())->assertRedirect();

        [$student, $enrollment] = app(TenantContext::class)->runFor($this->municipioA, fn (): array => [
            Student::query()->firstOrFail(),
            StudentEnrollment::query()->firstOrFail(),
        ]);

        $this->get($this->url('/'.$student->id.'/editar'))
            ->assertOk()
            ->assertSee($this->classA1Other);

        $payload = [
            'full_name' => $student->full_name,
            'birth_date' => '2018-04-05',
            'ra' => '987654321-X/SP',
            'school_class_id' => $this->classA1Other,
            'versao' => $student->updated_at->format('Y-m-d H:i:s'),
            'versao_matricula' => $enrollment->updated_at->format('Y-m-d H:i:s'),
        ];

        $this->travel(2)->seconds();
        $this->put($this->url('/'.$student->id), $payload)->assertRedirect();

        [$updated, $updatedEnrollment, $logs] = app(TenantContext::class)->runFor($this->municipioA, fn (): array => [
            Student::query()->findOrFail($student->id),
            StudentEnrollment::query()->findOrFail($enrollment->id),
            AuditLog::query()->where('auditable_type', StudentEnrollment::class)->where('auditable_id', $enrollment->id)->get(),
        ]);
        $this->assertSame('987654321-X/SP', $updated->ra);
        $this->assertSame($this->classA1Other, $updatedEnrollment->school_class_id);
        $this->assertTrue($logs->contains(fn (AuditLog $log): bool => ($log->before['school_class_id'] ?? null) === $this->classA1 && ($log->after['school_class_id'] ?? null) === $this->classA1Other));

        $this->put($this->url('/'.$student->id), array_replace($payload, ['ra' => '111111111-0/SP']))
            ->assertSessionHasErrors('versao');

        $this->assertSame('987654321-X/SP', app(TenantContext::class)->runFor($this->municipioA, fn (): string => Student::query()->findOrFail($student->id)->ra));
    }

    public function test_sem_permissao_ou_segundo_fator_nao_atribui_turma(): void
    {
        $this->entrar('teacher');
        $this->post($this->url(), $this->dados())->assertForbidden();

        $semFator = $this->criarUsuario('ra-sem-fator@example.invalid');
        $this->vincular($semFator, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);
        $this->actingAs($semFator)->post($this->url(), $this->dados())->assertForbidden();

        $this->assertSame(0, app(TenantContext::class)->runFor($this->municipioA, fn (): int => Student::query()->count()));
    }
}
