<?php

namespace Tests\Feature\Schools;

use App\Models\Tenant\Guardian;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentGuardian;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class SchoolClassRosterTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private string $yearId;

    private string $classId;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->yearId, $this->classId] = $this->inMunicipality(function (): array {
            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(),
                'year' => 2027,
                'start_date' => '2027-02-01',
                'end_date' => '2027-12-18',
                'status' => 'active',
            ]);
            $class = SchoolClass::query()->create([
                'id' => (string) Str::uuid(),
                'school_id' => self::ESCOLA_A1,
                'school_year_id' => $year->id,
                'name' => '1º Ano A',
                'stage' => 'Ensino Fundamental',
                'modality' => 'Regular',
                'shift' => 'Manhã',
                'capacity' => 2,
                'status' => 'active',
            ]);

            return [$year->id, $class->id];
        });
    }

    private function inMunicipality(callable $callback): mixed
    {
        return app(TenantContext::class)->runFor($this->municipioA, $callback);
    }

    private function login(string $role, array $schoolIds = []): void
    {
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schoolIds);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp()),
        ])->assertRedirect();
    }

    private function url(string $schoolId = self::ESCOLA_A1, ?string $classId = null): string
    {
        return '/m/municipio-a/escolas/'.$schoolId.'/turmas/'.($classId ?? $this->classId);
    }

    private function addClass(string $name, string $stage, ?int $capacity = null, string $schoolId = self::ESCOLA_A1): string
    {
        return $this->inMunicipality(fn (): string => SchoolClass::query()->create([
            'id' => (string) Str::uuid(),
            'school_id' => $schoolId,
            'school_year_id' => $this->yearId,
            'name' => $name,
            'stage' => $stage,
            'modality' => 'Regular',
            'shift' => 'Tarde',
            'capacity' => $capacity,
            'status' => 'active',
        ])->id);
    }

    private function addStudent(
        string $name,
        ?string $birthDate,
        string $status = 'active',
        ?string $classId = null,
        string $schoolId = self::ESCOLA_A1,
        ?string $contactName = null,
    ): string {
        return $this->inMunicipality(function () use ($name, $birthDate, $status, $classId, $schoolId, $contactName): string {
            $student = Student::query()->create([
                'id' => (string) Str::uuid(),
                'full_name' => $name,
                'birth_date' => $birthDate,
            ]);
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $student->id,
                'school_id' => $schoolId,
                'school_year_id' => $this->yearId,
                'school_class_id' => $classId ?? $this->classId,
                'status' => $status,
                'ended_at' => $status === 'active' ? null : '2027-04-01',
            ]);

            if ($contactName !== null) {
                $guardian = Guardian::query()->create([
                    'id' => (string) Str::uuid(),
                    'full_name' => $contactName,
                    'phone' => '11987654321',
                ]);
                StudentGuardian::query()->create([
                    'id' => (string) Str::uuid(),
                    'student_id' => $student->id,
                    'guardian_id' => $guardian->id,
                    'relationship' => 'mother',
                    'is_legal_guardian' => true,
                    'is_primary_contact' => true,
                ]);
            }

            return $student->id;
        });
    }

    public function test_lista_de_turmas_abre_ficha_com_alunos_ordenados_sem_acentos_idade_e_contato(): void
    {
        $this->login('school_secretary', [self::ESCOLA_A1]);
        $alvaro = $this->addStudent('Álvaro Fictício', '2021-03-31', contactName: 'Contato de Álvaro');
        $this->addStudent('Érica Fictícia', null);
        $this->addStudent('Bruna Fictícia', '2021-04-01');

        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/turmas')
            ->assertOk()
            ->assertSee($this->url())
            ->assertSee('1º Ano A');
        $response = $this->get($this->url())->assertOk()
            ->assertSee('Ensino Fundamental')
            ->assertSee('Manhã')
            ->assertSee('2027')
            ->assertSee('31/03/2027')
            ->assertSeeInOrder(['Álvaro Fictício', 'Bruna Fictícia', 'Érica Fictícia'])
            ->assertSee('31/03/2021')
            ->assertSee('01/04/2021')
            ->assertSee('6 anos')
            ->assertSee('5 anos')
            ->assertSee('Contato de Álvaro')
            ->assertSee('href="tel:+5511987654321"', false)
            ->assertSee('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$alvaro);
        $this->assertSame(1, substr_count($response->getContent(), 'Conferir idade'));
    }

    public function test_so_matriculas_ativas_da_turma_e_escola_entram_na_ocupacao_e_na_lista(): void
    {
        $this->login('school_director', [self::ESCOLA_A1]);
        $this->addStudent('Aluno ativo fictício', '2021-01-10');
        $this->addStudent('Aluno transferido fictício', '2021-01-10', status: 'transferred');
        $other = $this->addClass('2º Ano B', 'Ensino Fundamental');
        $this->addStudent('Aluno de outra turma', '2020-01-10', classId: $other);
        $otherSchoolClass = $this->addClass('3º Ano C', 'Ensino Fundamental', schoolId: self::ESCOLA_A2);
        $this->addStudent('Aluno de outra escola', '2021-01-10', classId: $otherSchoolClass, schoolId: self::ESCOLA_A2);

        $this->get($this->url())->assertOk()
            ->assertSee('1 de 2 vagas ocupadas')
            ->assertSee('Aluno ativo fictício')
            ->assertDontSee('Aluno transferido fictício')
            ->assertDontSee('Aluno de outra turma')
            ->assertDontSee('Aluno de outra escola')
            ->assertSee('role="progressbar"', false);
    }

    public function test_acima_da_capacidade_mostra_aviso_exato_e_barra_limitada_a_cem_por_cento(): void
    {
        $this->login('municipal_manager');
        $large = $this->addClass('5º Ano B', 'Ensino Fundamental', 25);
        for ($n = 1; $n <= 27; $n++) {
            $this->addStudent('Aluno fictício '.str_pad((string) $n, 2, '0', STR_PAD_LEFT), '2017-01-20', classId: $large);
        }

        $this->get($this->url(classId: $large))->assertOk()
            ->assertSee('27 de 25 vagas ocupadas')
            ->assertSee('Turma acima da capacidade: 27 alunos para 25 vagas.')
            ->assertSee('width: 100%', false)
            ->assertSee('108%');
    }

    public function test_idades_de_pre_escola_sao_conferidas_e_serie_desconhecida_nao_recebe_etiqueta(): void
    {
        $this->login('school_secretary', [self::ESCOLA_A1]);
        $preI = $this->addClass('Pré I A', 'Educação Infantil');
        $preII = $this->addClass('Pré II B', 'Educação Infantil');
        $preGenerica = $this->addClass('Pré-escola', 'Educação Infantil');
        $multi = $this->addClass('MULTI-1', 'Ensino Fundamental');
        $semVagas = $this->addClass('Turma experimental', 'Outra modalidade');

        $this->addStudent('Pré I com idade correta', '2023-03-31', classId: $preI);
        $this->addStudent('Pré I fora da idade', '2022-03-31', classId: $preI);
        $this->addStudent('Pré II com idade correta', '2022-03-31', classId: $preII);
        $this->addStudent('Pré II fora da idade', '2023-03-31', classId: $preII);
        $this->addStudent('Pré I sem nascimento', null, classId: $preI);
        $this->addStudent('Pré-escola idade válida', '2022-03-31', classId: $preGenerica);
        $this->addStudent('Pré-escola idade fora da faixa', '2020-03-31', classId: $preGenerica);
        $this->addStudent('Aluno multisseriado fictício', '2016-03-31', classId: $multi);
        $this->addStudent('Aluno sem etapa reconhecida', null, classId: $semVagas);

        $preIResponse = $this->get($this->url(classId: $preI))->assertOk();
        $this->assertSame(1, substr_count($preIResponse->getContent(), 'Conferir idade'));
        $preIIResponse = $this->get($this->url(classId: $preII))->assertOk();
        $this->assertSame(1, substr_count($preIIResponse->getContent(), 'Conferir idade'));
        $preGenericaResponse = $this->get($this->url(classId: $preGenerica))->assertOk();
        $this->assertSame(1, substr_count($preGenericaResponse->getContent(), 'Conferir idade'));
        $this->get($this->url(classId: $multi))->assertOk()->assertDontSee('Conferir idade');
        $this->get($this->url(classId: $semVagas))->assertOk()
            ->assertSee('Vagas não informadas')
            ->assertDontSee('Conferir idade')
            ->assertDontSee('role="progressbar"', false);
    }

    public function test_professor_ve_so_escola_autorizada_e_nao_pode_abrir_turma_de_outra_escola(): void
    {
        $other = $this->addClass('1º Ano de outra escola', 'Ensino Fundamental', schoolId: self::ESCOLA_A2);
        $this->addStudent('Aluno reservado de outra escola', '2021-01-01', classId: $other, schoolId: self::ESCOLA_A2);

        $this->login('teacher', [self::ESCOLA_A1]);
        $this->get($this->url())->assertOk()->assertDontSee('Editar turma');
        $this->get($this->url(self::ESCOLA_A2, $other))->assertForbidden();
        $this->get($this->url(classId: $other))->assertNotFound();

        $this->login('teacher', [self::ESCOLA_A2]);
        $this->get($this->url(self::ESCOLA_A2, $other))->assertOk()
            ->assertSee('Aluno reservado de outra escola');
        $this->get($this->url())->assertForbidden();
    }

    public function test_contatos_sao_carregados_em_lote_sem_consultas_por_aluno(): void
    {
        $this->login('school_secretary', [self::ESCOLA_A1]);
        for ($n = 1; $n <= 8; $n++) {
            $this->addStudent('Aluno fictício '.$n, '2021-01-10', contactName: 'Contato fictício '.$n);
        }

        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            if ($query->connectionName === 'tenant') {
                $queries[] = ['query' => $query->sql];
            }
        });
        $this->get($this->url())->assertOk()->assertSee('Contato fictício 8');

        $guardianLinkQueries = array_filter($queries, fn (array $query): bool => preg_match('/from\s+"?student_guardians"?/i', $query['query']) === 1);
        $guardianQueries = array_filter($queries, fn (array $query): bool => preg_match('/from\s+"?guardians"?/i', $query['query']) === 1);
        $this->assertCount(1, $guardianLinkQueries);
        $this->assertCount(1, $guardianQueries);
    }
}
