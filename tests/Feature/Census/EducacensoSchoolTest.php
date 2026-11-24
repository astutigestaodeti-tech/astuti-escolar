<?php

namespace Tests\Feature\Census;

use App\Educacenso\MontagemDaEscola;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolCensus;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolClassCensus;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class EducacensoSchoolTest extends TwoMunicipalityTestCase
{
    private string $yearId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-05-20 10:00:00'));
        $this->tenant(function (): void {
            $this->yearId = SchoolYear::query()->create([
                'id' => (string) Str::uuid(), 'year' => 2026, 'start_date' => '2026-02-01',
                'end_date' => '2026-12-18', 'status' => 'active',
            ])->id;
            SchoolCensus::query()->create(['school_id' => self::ESCOLA_A1, 'operating_status' => 1, 'administrative_dependency' => 3]);
            StaffMember::query()->create(['id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'full_name' => 'Gestora Fictícia', 'role' => 'director', 'status' => 'active']);
            $this->criarTurma(1);
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

    private function criarTurma(int $numero): void
    {
        $turma = SchoolClass::query()->create([
            'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'school_year_id' => $this->yearId,
            'name' => 'Turma Fictícia '.$numero, 'stage' => 'Ensino Fundamental', 'modality' => 'Regular',
            'shift' => 'Manhã', 'status' => 'active',
        ]);
        SchoolClassCensus::query()->create([
            'school_class_id' => $turma->id, 'inep_stage_code' => 16, 'mediation' => 1, 'class_type' => 6,
            'schedule_monday' => '07:00-11:00', 'differentiated_location' => 0, 'special_class' => false,
            'organization' => 1, 'alternance' => false, 'curricular_areas' => [3, 5], 'bilingual_deaf' => false,
        ]);
        foreach (range(1, 2) as $i) {
            $aluno = Student::query()->create([
                'id' => (string) Str::uuid(), 'full_name' => "Aluno Fictício {$numero} {$i}", 'birth_date' => '2018-03-01',
                'sex' => 'male', 'race_color' => 'parda', 'nationality' => 'brasileira', 'residence_zone' => 'urban',
            ]);
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $aluno->id, 'school_id' => self::ESCOLA_A1,
                'school_class_id' => $turma->id, 'school_year_id' => $this->yearId, 'status' => 'active', 'started_at' => '2026-02-01',
            ]);
        }
    }

    private function login(string $role, array $schools = [], bool $outroMunicipio = false): void
    {
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $outroMunicipio ? $this->municipioB : $this->municipioA, $role, $schools);
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', ['code' => app(Totp::class)->at($secret, now()->getTimestamp())])->assertRedirect();
    }

    private function url(): string
    {
        return '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/educacenso';
    }

    public function test_gestao_ve_pendencias_e_links_sem_valores_dos_registros(): void
    {
        $this->login('municipal_manager');
        $this->get('/m/municipio-a/conferencia-do-censo')->assertOk()->assertSee($this->url(), false);
        $this->get($this->url())->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Escola fora do arquivo')->assertSee('Registro 00')->assertSee('Registro 60')
            ->assertSee('Aluno Fictício 1 1')->assertSee('Corrigir cadastro')->assertDontSee('Parda');
    }

    public function test_escola_professor_e_outro_municipio_nao_acessam(): void
    {
        foreach (['school_director', 'school_secretary', 'teacher'] as $role) {
            $this->login($role, [self::ESCOLA_A1]);
            $this->get($this->url())->assertForbidden();
        }
        $this->login('municipal_manager', [], true);
        $this->assertContains($this->get($this->url())->status(), [403, 404]);
    }

    public function test_montagem_ordenada_e_consultas_nao_crescem_por_turma_ou_aluno(): void
    {
        $this->tenant(function (): void {
            $escola = School::query()->findOrFail(self::ESCOLA_A1);
            $ano = SchoolYear::query()->findOrFail($this->yearId);
            $montagem = app(MontagemDaEscola::class);
            $conexao = DB::connection('tenant');
            $conexao->enableQueryLog();
            try {
                $conexao->flushQueryLog();
                $primeira = $montagem->para($escola, $ano);
                $consultas = count($conexao->getQueryLog());
                foreach (range(2, 4) as $numero) {
                    $this->criarTurma($numero);
                }
                $conexao->flushQueryLog();
                $segunda = $montagem->para($escola, $ano);
                $this->assertSame($consultas, count($conexao->getQueryLog()));
            } finally {
                $conexao->disableQueryLog();
            }
            $this->assertSame(1, $primeira['contagens']['20']);
            $this->assertSame(4, $segunda['contagens']['20']);
            $this->assertSame(8, $segunda['contagens']['60']);
            $this->assertSame(9, $segunda['contagens']['30']);
            $tipos = array_column($segunda['registros'], 'registro');
            $ordenados = $tipos;
            sort($ordenados, SORT_STRING);
            $this->assertSame($ordenados, $tipos);
            $this->assertFalse($segunda['pronta']);
        });
    }

    public function test_escola_paralisada_exclui_turmas_alunos_e_vinculos_60(): void
    {
        $this->tenant(function (): void {
            $escola = School::query()->findOrFail(self::ESCOLA_A1);
            $ano = SchoolYear::query()->findOrFail($this->yearId);
            $censo = SchoolCensus::query()->findOrFail($escola->id);
            $censo->operating_status = 2;
            $censo->save();
            $resultado = app(MontagemDaEscola::class)->para($escola, $ano);
            $this->assertSame(['00', '30', '40'], array_column($resultado['registros'], 'registro'));
        });
    }
}
