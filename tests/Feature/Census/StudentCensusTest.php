<?php

namespace Tests\Feature\Census;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\Guardian;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentCensus;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentGuardian;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class StudentCensusTest extends TwoMunicipalityTestCase
{
    private string $studentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2027-05-20 10:00:00'));
        $this->studentId = $this->tenant(function (): string {
            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(), 'year' => 2027,
                'start_date' => '2027-02-01', 'end_date' => '2027-12-15', 'status' => 'active',
            ]);
            $student = Student::query()->create([
                'id' => (string) Str::uuid(), 'full_name' => 'Estudante Fictícia Censo', 'birth_date' => '2018-03-01',
                'nationality' => 'brasileira', 'birthplace_city' => 'Sao Paulo', 'birthplace_state' => 'SP',
                'address_city' => 'Santa Isabel', 'address_state' => 'SP', 'residence_zone' => 'urban',
            ]);
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $student->id, 'school_id' => self::ESCOLA_A1,
                'school_year_id' => $year->id, 'status' => 'active', 'started_at' => '2027-02-01',
            ]);
            $mae = Guardian::query()->create(['id' => (string) Str::uuid(), 'full_name' => 'Mãe Fictícia de Souza', 'phone' => '11999990000']);
            StudentGuardian::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $student->id, 'guardian_id' => $mae->id,
                'relationship' => 'mother', 'is_legal_guardian' => true, 'is_primary_contact' => true,
            ]);

            return $student->id;
        });
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function tenant(callable $callback): mixed
    {
        return app(TenantContext::class)->runFor($this->municipioA, $callback);
    }

    private function login(string $role, array $schools = [], bool $twoFactor = true): void
    {
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schools);
        if (! $twoFactor) {
            $this->actingAs($user);

            return;
        }
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at($secret, now()->getTimestamp()),
        ])->assertRedirect();
    }

    private function url(?string $school = null): string
    {
        return '/m/municipio-a/escolas/'.($school ?? self::ESCOLA_A1).'/alunos/'.$this->studentId.'/censo';
    }

    private function valid(array $override = []): array
    {
        return array_replace([
            'filiacao' => '1', 'filiacao_1' => 'Mãe Fictícia de Souza', 'filiacao_2' => '',
            'nascimento_uf' => 'SP', 'nascimento_municipio' => 'São Paulo',
            'residencia_pais' => '76', 'residencia_uf' => 'SP', 'residencia_municipio' => 'santa isabel',
            'localizacao_diferenciada' => '7',
        ], $override);
    }

    public function test_secretaria_ve_sugestoes_e_grava_codigos_oficiais_com_auditoria_sem_valores(): void
    {
        $this->login('school_secretary', [self::ESCOLA_A1]);

        $this->get($this->url())->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Primeiro preenchimento')
            ->assertSee('value="Mãe Fictícia de Souza"', false)
            ->assertSee('value="São Paulo"', false)
            ->assertSee('value="Santa Isabel"', false)
            ->assertSee('Na tabela do Inep: Santa Isabel/SP, código 3546801.');

        $this->put($this->url(), $this->valid())->assertRedirect();

        [$census, $log] = $this->tenant(fn (): array => [
            StudentCensus::query()->findOrFail($this->studentId),
            AuditLog::query()->where('action', 'student_census.created')->sole(),
        ]);
        $this->assertSame(1, $census->filiation);
        $this->assertSame('Mãe Fictícia de Souza', $census->parent1_name);
        $this->assertNull($census->parent2_name);
        $this->assertSame(76, $census->nationality_country_code);
        $this->assertSame(3550308, $census->birth_municipality_code);
        $this->assertSame(76, $census->residence_country_code);
        $this->assertSame(3546801, $census->residence_municipality_code);
        $this->assertSame(7, $census->differentiated_location);
        $this->assertNull($census->indigenous_people_code);
        $this->assertStringNotContainsString('Souza', json_encode($log->new_values ?? $log->toArray()));

        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$this->studentId)->assertOk()
            ->assertSee('Dados para o Censo Escolar')->assertSee('São Paulo/SP')->assertSee('Santa Isabel/SP');
    }

    public function test_filiacao_nao_declarada_apaga_os_nomes_e_nome_sozinho_segue_a_regra_do_inep(): void
    {
        $this->login('school_secretary', [self::ESCOLA_A1]);

        $this->put($this->url(), $this->valid(['filiacao_1' => 'M. Souza']))
            ->assertSessionHasErrors(['filiacao_1']);
        $this->put($this->url(), $this->valid(['filiacao_1' => '', 'filiacao_2' => '']))
            ->assertSessionHasErrors(['filiacao_1']);

        $this->put($this->url(), $this->valid(['filiacao' => '0', 'filiacao_1' => 'Mãe Fictícia de Souza']))->assertRedirect();
        $census = $this->tenant(fn () => StudentCensus::query()->findOrFail($this->studentId));
        $this->assertSame(0, $census->filiation);
        $this->assertNull($census->parent1_name);
    }

    public function test_municipio_que_nao_existe_na_tabela_e_localizacao_incompativel_sao_recusados(): void
    {
        $this->login('school_secretary', [self::ESCOLA_A1]);

        $this->put($this->url(), $this->valid(['nascimento_municipio' => 'Monte Sereno', 'nascimento_uf' => 'SP']))
            ->assertSessionHasErrors(['nascimento_municipio']);
        $this->put($this->url(), $this->valid(['localizacao_diferenciada' => '1']))
            ->assertSessionHasErrors(['localizacao_diferenciada']);
        $this->assertNull($this->tenant(fn () => StudentCensus::query()->find($this->studentId)));
    }

    public function test_estrangeiro_indigena_e_residente_no_exterior_seguem_as_regras_do_registro_30(): void
    {
        $this->tenant(fn () => Student::query()->findOrFail($this->studentId)->forceFill(['nationality' => 'estrangeira', 'race_color' => 'indigena'])->save());
        $this->login('school_director', [self::ESCOLA_A1]);

        $this->put($this->url(), $this->valid(['pais_nacionalidade' => '76']))->assertSessionHasErrors(['pais_nacionalidade']);

        $this->put($this->url(), $this->valid([
            'pais_nacionalidade' => '600', 'povo_indigena' => '1',
            'residencia_pais' => '600', 'residencia_municipio' => 'Santa Isabel', 'localizacao_diferenciada' => '7',
        ]))->assertRedirect();

        $census = $this->tenant(fn () => StudentCensus::query()->findOrFail($this->studentId));
        $this->assertSame(600, $census->nationality_country_code);
        $this->assertNull($census->birth_municipality_code);
        $this->assertSame(1, $census->indigenous_people_code);
        $this->assertSame(600, $census->residence_country_code);
        $this->assertNull($census->residence_municipality_code);
        $this->assertNull($census->differentiated_location);
    }

    public function test_professor_outra_escola_e_sem_segundo_fator_nao_acessam(): void
    {
        $this->login('teacher', [self::ESCOLA_A1]);
        $this->get($this->url())->assertForbidden();

        $this->login('school_secretary', [self::ESCOLA_A2]);
        $this->get($this->url(self::ESCOLA_A2))->assertForbidden();

        $this->login('school_secretary', [self::ESCOLA_A1], false);
        $this->get($this->url())->assertRedirect();
        $this->put($this->url(), $this->valid())->assertForbidden();
        $this->assertNull($this->tenant(fn () => StudentCensus::query()->find($this->studentId)));
    }
}
