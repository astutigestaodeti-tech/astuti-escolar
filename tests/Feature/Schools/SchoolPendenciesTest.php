<?php

namespace Tests\Feature\Schools;

use App\Models\Tenant\Guardian;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentGuardian;
use App\Security\Totp;
use App\Students\RegistrationPendencies;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class SchoolPendenciesTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    /** @var array<string, string> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = $this->tenant(function (): array {
            // Escola no mapa: o sistema confere também transporte e localização da casa.
            School::query()->whereKey(self::ESCOLA_A1)->firstOrFail()->forceFill(['latitude' => -23.3160, 'longitude' => -46.2230, 'geo_source' => 'mapa', 'geo_updated_at' => now()])->save();
            $year = SchoolYear::query()->create(['id' => (string) Str::uuid(), 'year' => 2027, 'start_date' => '2027-02-01', 'end_date' => '2027-12-18', 'status' => 'active']);
            $class = SchoolClass::query()->create(['id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id, 'name' => '4º Ano A', 'stage' => 'Ensino Fundamental', 'modality' => 'Regular', 'shift' => 'Tarde', 'status' => 'active']);

            $make = function (string $name, array $data = [], bool $withClass = true, ?string $transport = null, string $school = self::ESCOLA_A1) use ($year, $class): string {
                $student = new Student;
                $student->forceFill(['id' => (string) Str::uuid(), 'full_name' => $name, ...$data])->save();
                StudentEnrollment::query()->create(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'school_id' => $school, 'school_year_id' => $year->id, 'school_class_id' => $withClass && $school === self::ESCOLA_A1 ? $class->id : null, 'status' => 'active', 'transport_status' => $transport]);

                return $student->id;
            };
            $guardian = function (string $student, string $phone): void {
                $g = Guardian::query()->create(['id' => (string) Str::uuid(), 'full_name' => 'Responsável Fictício '.Str::random(4), 'phone' => $phone]);
                StudentGuardian::query()->create(['id' => (string) Str::uuid(), 'student_id' => $student, 'guardian_id' => $g->id, 'relationship' => 'mother', 'is_primary_contact' => true]);
            };
            $completo = ['birth_date' => '2017-05-10', 'address_street' => 'Rua Fictícia', 'address_number' => '1', 'postal_code' => '01001000', 'photo_path' => 'fotos/ficticia.jpg', 'latitude' => -23.3165, 'longitude' => -46.2235, 'geo_source' => 'mapa'];

            $ids = [];
            $ids['completo'] = $make('Ana Completa Fictícia', $completo);
            $guardian($ids['completo'], '11987654321');
            $ids['sem_responsavel'] = $make('Bruno Sem Responsável', $completo);
            $ids['segundo_sem_responsavel'] = $make('Carla Sem Responsável', $completo);
            $ids['sem_turma'] = $make('Davi Sem Turma', $completo, withClass: false);
            $guardian($ids['sem_turma'], '11987654321');
            // Mora a cerca de 3,3 km e não usa transporte: conferir com a família.
            $ids['longe'] = $make('Eva Mora Longe', [...$completo, 'latitude' => -23.2870, 'longitude' => -46.2230]);
            $guardian($ids['longe'], '11987654321');
            $ids['incompleto'] = $make('Fábio Cadastro Incompleto', ['address_street' => null]);
            $guardian($ids['incompleto'], '11987654321');
            $ids['outra_escola'] = $make('Gabi de Outra Escola', [], school: self::ESCOLA_A2);

            return $ids;
        });
    }

    private function tenant(callable $callback): mixed
    {
        return app(TenantContext::class)->runFor($this->municipioA, $callback);
    }

    private function login(string $role, string $school = self::ESCOLA_A1): void
    {
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, [$school]);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', ['code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp())])->assertRedirect();
    }

    private function url(string $query = ''): string
    {
        return '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/pendencias'.$query;
    }

    public function test_aponta_cada_pendencia_com_o_atalho_certo_e_prioriza_contato_com_a_familia(): void
    {
        $result = $this->tenant(fn () => collect(app(RegistrationPendencies::class)->forSchool(School::query()->findOrFail(self::ESCOLA_A1)))->keyBy('name')->map(fn ($s) => $s['pendencies'])->all());

        $this->assertSame([], $result['Ana Completa Fictícia']);
        $this->assertSame(['responsavel'], $result['Bruno Sem Responsável']);
        $this->assertSame(['responsavel'], $result['Carla Sem Responsável']);
        $this->assertSame(['turma'], $result['Davi Sem Turma']);
        $this->assertSame(['transporte'], $result['Eva Mora Longe']);
        $this->assertSame(['nascimento', 'endereco', 'foto', 'mapa'], $result['Fábio Cadastro Incompleto']);
        $this->assertArrayNotHasKey('Gabi de Outra Escola', $result);

        $this->login('school_secretary');
        $this->get($this->url())->assertOk()
            ->assertSee('1 de 6 alunos sem nenhuma pendência.')
            ->assertSeeInOrder(['Bruno Sem Responsável', 'Carla Sem Responsável', 'Davi Sem Turma', 'Fábio Cadastro Incompleto', 'Eva Mora Longe'])
            ->assertDontSee('Ana Completa Fictícia')
            ->assertSee('/alunos/'.$this->ids['sem_responsavel'].'/responsaveis/novo', false)
            ->assertSee('/alunos/'.$this->ids['incompleto'].'/localizacao', false)
            ->assertSee('Conferir transporte');

        $this->get($this->url('?tipo=turma'))->assertOk()->assertSee('Davi Sem Turma')->assertDontSee('Bruno Sem Responsável');
        $this->get($this->url('?tipo=qualquer'))->assertSessionHasErrors('tipo');
    }

    public function test_avisa_possivel_cadastro_repetido_sem_confundir_homonimos(): void
    {
        $this->tenant(function (): void {
            $year = SchoolYear::query()->where('year', 2027)->firstOrFail();
            foreach ([['Júlia  Repetida Fictícia', null], ['julia repetida ficticia', '2016-03-02'], ['Homônimo Fictício', '2015-01-01'], ['Homonimo Ficticio', '2016-01-01']] as [$name, $birth]) {
                $student = new Student;
                $student->forceFill(['id' => (string) Str::uuid(), 'full_name' => $name, 'birth_date' => $birth])->save();
                StudentEnrollment::query()->create(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id, 'status' => 'active']);
            }
        });
        $result = $this->tenant(fn () => collect(app(RegistrationPendencies::class)->forSchool(School::query()->findOrFail(self::ESCOLA_A1)))->keyBy('name')->map(fn ($s) => $s['pendencies'])->all());

        // Mesmo nome e um nascimento em branco: pode ser o mesmo aluno. Nascimentos diferentes: são pessoas diferentes.
        $this->assertSame('repetido', $result['Júlia  Repetida Fictícia'][0]);
        $this->assertSame('repetido', $result['julia repetida ficticia'][0]);
        $this->assertNotContains('repetido', $result['Homônimo Fictício']);
        $this->assertNotContains('repetido', $result['Homonimo Ficticio']);

        $this->login('school_secretary');
        $this->get($this->url('?tipo=repetido'))->assertOk()
            ->assertSee('Possível cadastro repetido')->assertSee('Comparar os cadastros')
            ->assertDontSee('Homônimo Fictício');
    }

    public function test_resumo_conta_alunos_distintos_e_atualiza_apos_corrigir_pendencia(): void
    {
        $this->tenant(function (): void {
            $service = app(RegistrationPendencies::class);
            $school = School::query()->findOrFail(self::ESCOLA_A1);
            $summary = $service->summary($service->forSchool($school));
            $this->assertSame([
                'total' => 6,
                'complete' => 1,
                'withPendencies' => 5,
                'byType' => [
                    'repetido' => 0, 'responsavel' => 2, 'turma' => 1, 'nascimento' => 1,
                    'endereco' => 1, 'transporte' => 1, 'foto' => 1, 'mapa' => 1,
                ],
            ], $summary);

            $guardian = Guardian::query()->create([
                'id' => (string) Str::uuid(), 'full_name' => 'Responsável Fictício Teste', 'phone' => '11987654321',
            ]);
            StudentGuardian::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $this->ids['sem_responsavel'],
                'guardian_id' => $guardian->id, 'relationship' => 'mother', 'is_primary_contact' => true,
            ]);

            $after = $service->summary($service->forSchool($school));
            $this->assertSame(6, $after['total']);
            $this->assertSame(2, $after['complete']);
            $this->assertSame(4, $after['withPendencies']);
            $this->assertSame([...$summary['byType'], 'responsavel' => 1], $after['byType']);
            $this->assertSame(1, $after['byType']['nascimento']);
        });
    }

    public function test_calcula_a_escola_inteira_em_uma_consulta(): void
    {
        $queries = $this->tenant(function (): int {
            $school = School::query()->findOrFail(self::ESCOLA_A1);
            DB::connection('tenant')->enableQueryLog();
            app(RegistrationPendencies::class)->forSchool($school);

            return count(DB::connection('tenant')->getQueryLog());
        });

        $this->assertSame(1, $queries);
    }

    public function test_cartao_da_escola_mostra_o_total_e_so_a_gestao_acessa(): void
    {
        $this->login('school_director');
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1)->assertOk()
            ->assertSee('Pendências do cadastro')->assertSee('5 alunos')->assertSee('Ver o que falta completar');

        $this->login('teacher');
        $this->get($this->url())->assertForbidden();
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1)->assertOk()->assertDontSee('Ver o que falta completar');

        $this->login('school_secretary', self::ESCOLA_A2);
        $this->get($this->url())->assertForbidden();
    }
}
