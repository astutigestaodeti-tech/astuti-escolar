<?php

namespace Tests\Feature\Census;

use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolClassCensus;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentCensus;
use App\Models\Tenant\StudentEnrollment;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class StudentsCensusTest extends TwoMunicipalityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-05-20 10:00:00'));
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            DB::connection('tenant')->table('schools')->where('id', self::ESCOLA_A1)->update(['inep_code' => '35999001']);
            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(), 'year' => 2026,
                'start_date' => '2026-02-01', 'end_date' => '2026-12-15', 'status' => 'active',
            ]);
            $class = SchoolClass::query()->create([
                'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id,
                'name' => '3º Ano A', 'stage' => 'Ensino Fundamental', 'modality' => 'Regular', 'shift' => 'Manhã', 'status' => 'active',
            ]);
            SchoolClassCensus::query()->create([
                'school_class_id' => $class->id, 'inep_stage_code' => 16, 'mediation' => 1, 'class_type' => 6,
                'schedule_monday' => '07:00-11:30', 'differentiated_location' => 0, 'special_class' => false,
                'organization' => 1, 'alternance' => false, 'curricular_areas' => [3, 5], 'bilingual_deaf' => false,
            ]);
            foreach (['Ana Fictícia Pronta', 'Bruno Fictício Pendente'] as $i => $nome) {
                $student = Student::query()->create([
                    'id' => (string) Str::uuid(), 'full_name' => $nome, 'birth_date' => '2018-03-01', 'sex' => 'female',
                    'race_color' => 'parda', 'nationality' => 'brasileira', 'residence_zone' => 'urban',
                ]);
                StudentEnrollment::query()->create([
                    'id' => (string) Str::uuid(), 'student_id' => $student->id, 'school_id' => self::ESCOLA_A1, 'school_class_id' => $class->id,
                    'school_year_id' => $year->id, 'status' => 'active', 'started_at' => '2026-02-01', 'other_schooling_space' => 1,
                ]);
                if ($i === 0) {
                    StudentCensus::query()->create([
                        'student_id' => $student->id, 'filiation' => 0, 'nationality_country_code' => 76,
                        'birth_municipality_code' => 3550308, 'residence_country_code' => 76, 'differentiated_location' => 7,
                    ]);
                }
            }
        });
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function login(string $role, array $schools = []): void
    {
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schools);
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at($secret, now()->getTimestamp()),
        ])->assertRedirect();
    }

    public function test_secretaria_ve_quem_esta_pronto_e_o_que_falta_sem_dados_sensiveis(): void
    {
        $this->login('school_secretary', [self::ESCOLA_A1]);
        $url = '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/censo/alunos';

        $html = $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Alunos no Censo')->assertSee('1 de 2 prontos')
            ->assertSee('Ana Fictícia Pronta')->assertSee('Pronto')
            ->assertSee('Bruno Fictício Pendente')
            ->assertSee('Os dados do Censo do aluno ainda não foram preenchidos')
            ->assertDontSee('Parda')
            ->getContent();
        if ($dump = getenv('ASTUTI_DUMP_TELAS')) {
            $html = str_replace(rtrim((string) config('app.url'), '/').'/', 'file://'.public_path().'/', $html);
            file_put_contents(rtrim($dump, '/').'/censo_alunos.html', preg_replace('/\.(css|js)\?v=\d+/', '.$1', $html));
        }
        $this->get($url.'?situacao=pendentes')->assertOk()->assertDontSee('Ana Fictícia Pronta')->assertSee('Bruno Fictício Pendente');
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos')->assertOk()->assertSee('Alunos no Censo');
    }

    public function test_conferencia_da_rede_mostra_alunos_prontos_e_professor_nao_acessa(): void
    {
        $this->login('municipal_manager');
        $this->get('/m/municipio-a/conferencia-do-censo')->assertOk()
            ->assertSee('Alunos prontos para o Educacenso')->assertSee('1 de 2');

        $this->login('teacher', [self::ESCOLA_A1]);
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/censo/alunos')->assertForbidden();
    }
}
