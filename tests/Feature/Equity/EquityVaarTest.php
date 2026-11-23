<?php

namespace Tests\Feature\Equity;

use App\Models\Tenant\AcademicPeriod;
use App\Models\Tenant\AttendanceMark;
use App\Models\Tenant\AttendanceSession;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\CurricularComponent;
use App\Models\Tenant\GradeEntry;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentFinalResult;
use App\Models\Tenant\StudentSpecialEducation;
use App\Models\Tenant\VaarCondition;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class EquityVaarTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private const URL = '/m/municipio-a/equidade-vaar';

    private string $yearId;

    private string $periodId;

    private string $componentId;

    /** @var array<string, string> */
    private array $classes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2027-10-15 15:00:00'));
        $this->tenant(function (): void {
            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(),
                'year' => 2027,
                'start_date' => '2027-02-01',
                'end_date' => '2027-12-15',
                'status' => 'active',
            ]);
            $this->yearId = $year->id;
            $this->periodId = AcademicPeriod::query()
                ->where('school_year_id', $year->id)->orderBy('number')->firstOrFail()->id;
            $this->componentId = CurricularComponent::query()
                ->where('code', 'matematica')->sole()->id;
            $this->classes = [];
            foreach ([
                'segundo' => [self::ESCOLA_A1, '2º Ano A'],
                'quinto' => [self::ESCOLA_A1, '5º Ano A'],
                'nono' => [self::ESCOLA_A2, '9º Ano A'],
            ] as $key => [$school, $name]) {
                $this->classes[$key] = SchoolClass::query()->create([
                    'id' => (string) Str::uuid(),
                    'school_id' => $school, 'school_year_id' => $year->id,
                    'name' => $name, 'stage' => 'Ensino Fundamental',
                    'modality' => 'Regular', 'shift' => 'Manhã', 'status' => 'active',
                ])->id;
                foreach (['2027-10-13', '2027-10-14'] as $day) {
                    AttendanceSession::query()->create([
                        'id' => (string) Str::uuid(),
                        'school_class_id' => $this->classes[$key],
                        'attendance_date' => $day, 'recorded_at' => now(),
                        'recorded_by' => $this->auditFixtureUser->id,
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

    private function tenant(callable $callback): mixed
    {
        return app(TenantContext::class)->runFor($this->municipioA, $callback);
    }

    private function login(string $role, array $schools = []): void
    {
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schools);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp()),
        ])->assertRedirect();
    }

    /** Aluno e matrícula totalmente fictícios, com uma marca por chamada existente. */
    private function student(
        int $number,
        string $classKey,
        ?string $race,
        string $sex = 'female',
        string $zone = 'urban',
        ?float $grade = 7.0,
        array $daysPresent = [true, true],
        ?string $result = null,
    ): string {
        return $this->tenant(function () use (
            $number, $classKey, $race, $sex, $zone, $grade, $daysPresent, $result,
        ): string {
            $classId = $this->classes[$classKey];
            $class = SchoolClass::query()->findOrFail($classId);
            $student = Student::query()->create([
                'id' => (string) Str::uuid(), 'full_name' => 'Estudante Fictício '.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'race_color' => $race, 'sex' => $sex, 'residence_zone' => $zone,
            ]);
            $enrollment = StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $student->id,
                'school_id' => $class->school_id, 'school_year_id' => $this->yearId,
                'school_class_id' => $class->id, 'status' => 'active',
            ]);
            if ($grade !== null) {
                GradeEntry::query()->create([
                    'id' => (string) Str::uuid(), 'student_enrollment_id' => $enrollment->id,
                    'school_class_id' => $class->id, 'academic_period_id' => $this->periodId,
                    'curricular_component_id' => $this->componentId,
                    'grade' => (string) $grade,
                    'recorded_by' => $this->auditFixtureUser->id,
                    'updated_by' => $this->auditFixtureUser->id,
                ]);
            }
            $sessions = AttendanceSession::query()->where('school_class_id', $classId)->orderBy('attendance_date')->get();
            foreach ($sessions as $index => $session) {
                AttendanceMark::query()->create([
                    'id' => (string) Str::uuid(),
                    'attendance_session_id' => $session->id,
                    'student_id' => $student->id,
                    'status' => ($daysPresent[$index] ?? false) ? 'present' : 'absent',
                ]);
            }
            if ($result !== null) {
                StudentFinalResult::query()->create([
                    'id' => (string) Str::uuid(), 'school_class_id' => $classId,
                    'student_enrollment_id' => $enrollment->id,
                    'suggested_result' => $result, 'result' => $result,
                    'confirmed_by' => $this->auditFixtureUser->id,
                    'confirmed_at' => now(),
                ]);
            }

            return $student->id;
        });
    }

    public function test_acesso_exclusivo_gestao_e_direcao_somente_escola(): void
    {
        $this->get(self::URL)->assertRedirect('/login');

        foreach (['teacher', 'school_secretary'] as $role) {
            $this->login($role, [self::ESCOLA_A1]);
            $this->get(self::URL)->assertForbidden();
            $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/equidade-vaar')->assertForbidden();
        }

        $this->login('school_director', [self::ESCOLA_A1]);
        $this->get(self::URL)->assertForbidden();
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/equidade-vaar')
            ->assertOk()->assertSee('Painel de equidade e VAAR')->assertDontSee('Condicionalidades I a V');
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A2.'/equidade-vaar')->assertForbidden();

        $this->login('municipal_manager');
        $this->get(self::URL)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Condicionalidades I a V')
            ->assertSee('Equidade e VAAR');
        $this->get(self::URL.'?escola='.self::ESCOLA_A1)->assertOk();
        $this->get(self::URL.'?escola='.self::ESCOLA_A1.'&turma='.$this->classes['nono'])
            ->assertNotFound();
        $this->get(self::URL.'?ano=2030')->assertSessionHasErrors('ano');
    }

    public function test_celulas_pequenas_e_suprimidas_por_complemento_nunca_exibem_numeros(): void
    {
        foreach (range(1, 4) as $number) {
            $this->student($number, 'segundo', 'preta', grade: 8.0);
        }
        foreach (range(5, 10) as $number) {
            $this->student($number, 'segundo', 'branca', grade: 6.0);
        }
        $this->login('municipal_manager');
        $response = $this->get(self::URL.'?ano=2027&escola='.self::ESCOLA_A1.'&turma='.$this->classes['segundo'])
            ->assertOk()->assertSee('grupo pequeno')->assertSee('Preta + parda + indígena');

        $groups = $response->viewData('data')['groups']['raca']['rows'];
        $black = collect($groups)->firstWhere('label', 'Preta');
        $white = collect($groups)->firstWhere('label', 'Branca');
        $combined = collect($groups)->firstWhere('label', 'Preta + parda + indígena');
        $this->assertTrue($black['suppressed']);
        $this->assertTrue($white['suppressed']); // supressão complementar
        $this->assertTrue($combined['suppressed']);
        $this->assertNull($black['metrics']);
        $this->assertNull($white['metrics']);
        $this->assertNull($combined['metrics']);

        $this->assertSame(10, $response->viewData('data')['summary']['count']);
        $this->assertSame(6.8, $response->viewData('data')['summary']['average']);
        $this->assertSame(100.0, $response->viewData('data')['summary']['attendance']);
    }

    public function test_grupos_grandes_mostram_medias_e_so_resultados_confirmados(): void
    {
        foreach (range(1, 5) as $number) {
            $this->student($number, 'quinto', 'preta', result: 'retido');
        }
        foreach (range(6, 10) as $number) {
            $this->student($number, 'quinto', 'parda', result: 'aprovado');
        }
        $this->login('municipal_manager');
        $response = $this->get(self::URL.'?ano=2027&escola='.self::ESCOLA_A1.'&turma='.$this->classes['quinto'])
            ->assertOk();
        $groups = collect($response->viewData('data')['groups']['raca']['rows'])->keyBy('label');
        $this->assertSame(5, $groups['Preta']['metrics']['count']);
        $this->assertSame(5, $groups['Parda']['metrics']['count']);
        $this->assertSame(10, $groups['Preta + parda + indígena']['metrics']['count']);
        $this->assertSame(7.0, $groups['Preta']['metrics']['average']);
        $this->assertSame(5, $groups['Preta']['metrics']['retained']);
        $this->assertSame(0, $groups['Parda']['metrics']['retained']);
    }

    public function test_medias_com_menos_de_cinco_lancamentos_nao_sao_expostas(): void
    {
        foreach (range(1, 6) as $number) {
            $this->student($number, 'quinto', 'parda', grade: $number === 1 ? 9.0 : null);
        }
        $this->login('municipal_manager');
        $response = $this->get(self::URL.'?ano=2027&escola='.self::ESCOLA_A1.'&turma='.$this->classes['quinto'])
            ->assertOk();
        $race = collect($response->viewData('data')['groups']['raca']['rows'])->keyBy('label');
        $this->assertSame(6, $race['Parda']['metrics']['count']);
        $this->assertNull($race['Parda']['metrics']['average']);
        $this->assertSame(100.0, $race['Parda']['metrics']['attendance']);
        $this->assertStringNotContainsString('9,0', $response->getContent());
    }

    public function test_acompanhamento_saeb_tem_nomes_somente_na_lista_de_faltas_sem_cor_raca(): void
    {
        $this->student(1, 'segundo', 'indigena', daysPresent: [false, false]);
        $this->student(2, 'quinto', 'preta', daysPresent: [false, true]);
        $this->student(3, 'nono', 'parda', daysPresent: [true, false]);
        $this->login('municipal_manager');
        $response = $this->get(self::URL.'?ano=2027')->assertOk()
            ->assertSee('Estudante Fictício 01')->assertSee('Estudante Fictício 02')
            ->assertSee('Estudante Fictício 03')
            ->assertSee('Frequência às aulas não é taxa de participação no Saeb');
        $saeb = $response->viewData('data')['saeb'];
        $this->assertSame([2, 1, 1], $saeb->pluck('absences')->all());
        $this->assertSame(['name', 'school', 'class', 'absences', 'attendance'], array_keys($saeb->first()));

        $this->login('school_director', [self::ESCOLA_A1]);
        $school = $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/equidade-vaar?ano=2027')->assertOk();
        $school->assertSee('Estudante Fictício 01')->assertDontSee('Estudante Fictício 03');
        $this->assertCount(2, $school->viewData('data')['saeb']);
    }

    public function test_condicionalidades_com_pdf_privado_e_auditoria_sem_caminho_ou_alunos(): void
    {
        Storage::fake('local');
        $this->login('municipal_manager');
        $url = self::URL.'/condicionalidades/2';
        $this->post($url, [
            'ano' => 2027,
            'status' => 'a_comprovar',
            'responsavel' => 'Coordenação de Avaliação',
            'documento' => UploadedFile::fake()->create('relatorio.pdf', 20, 'application/pdf'),
        ])->assertRedirect();

        $condition = $this->tenant(fn (): VaarCondition => VaarCondition::query()->sole());
        $this->assertSame(2, $condition->condition_number);
        $this->assertSame('a_comprovar', $condition->status);
        $this->assertSame('Coordenação de Avaliação', $condition->responsible);
        $this->assertNotNull($condition->evidence_path);
        Storage::disk('local')->assertExists($condition->evidence_path);

        $this->get(self::URL.'/condicionalidades/2/documento?ano=2027')->assertOk();
        $log = $this->tenant(fn () => AuditLog::query()->where('action', 'equity.vaar.condition.updated')->sole());
        $this->assertTrue($log->after['has_evidence']);
        $this->assertStringNotContainsString('relatorio.pdf', $log->toJson());
        $this->assertStringNotContainsString($condition->evidence_path, $log->toJson());

        $this->post($url, [
            'ano' => 2027, 'status' => 'cumprida',
            'responsavel' => 'Diretoria Pedagógica',
        ])->assertRedirect();
        $this->assertSame(1, $this->tenant(fn (): int => VaarCondition::query()->count()));

        $this->post($url, [
            'ano' => 2027, 'status' => 'pendente',
            'responsavel' => 'Diretoria Pedagógica',
            'remover_documento' => '1',
        ])->assertRedirect();
        Storage::disk('local')->assertMissing($condition->evidence_path);
    }

    public function test_apenas_gestao_escreve_e_segundo_fator_recente_e_obrigatorio(): void
    {
        $this->login('school_director', [self::ESCOLA_A1]);
        $this->post(self::URL.'/condicionalidades/1', [
            'ano' => 2027, 'status' => 'cumprida', 'responsavel' => 'Escola A',
        ])->assertForbidden();

        $this->login('municipal_manager');
        $this->travelTo(now()->addMinutes(20));
        $this->post(self::URL.'/condicionalidades/1', [
            'ano' => 2027, 'status' => 'cumprida', 'responsavel' => 'Gabinete',
        ])->assertRedirect(route('two-factor.challenge'));
        $this->assertSame(0, $this->tenant(fn (): int => VaarCondition::query()->count()));
    }

    public function test_migracao_limita_numero_e_situacoes_indevidas(): void
    {
        $this->tenant(function (): void {
            try {
                DB::connection('tenant')->table('vaar_conditions')->insert([
                    'id' => (string) Str::uuid(), 'reference_year' => 2027,
                    'condition_number' => 6, 'status' => 'cumprida',
                    'responsible' => 'Teste', 'updated_by' => $this->auditFixtureUser->id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->fail('O banco deveria impedir uma sexta condicionalidade.');
            } catch (QueryException $e) {
                $this->assertStringContainsString('vaar_conditions_number_check', $e->getMessage());
            }
        });
    }

    public function test_deficiencia_e_tea_estruturados_sao_apenas_agregados_com_cinco_ou_mais(): void
    {
        foreach (range(1, 5) as $number) {
            $id = $this->student($number, 'segundo', 'parda');
            $this->tenant(fn () => StudentSpecialEducation::query()->create([
                'student_id' => $id,
                'conditions' => [$number === 1 ? 'tea' : 'deficiencia_intelectual'],
                'exam_resources' => [],
                'updated_by' => $this->auditFixtureUser->id,
            ]));
        }
        foreach (range(6, 10) as $number) {
            $this->student($number, 'segundo', 'branca');
        }

        $this->login('municipal_manager');
        $response = $this->get(self::URL.'?ano=2027&escola='.self::ESCOLA_A1.'&turma='.$this->classes['segundo'])
            ->assertOk()->assertSee('Deficiência e TEA (informados)');
        $rows = collect($response->viewData('data')['groups']['deficiencia']['rows'])->keyBy('label');
        $this->assertSame(5, $rows['Deficiência ou TEA registrados']['metrics']['count']);
        $this->assertSame(5, $rows['Sem deficiência ou TEA registrados (não conclusivo)']['metrics']['count']);
        $this->assertStringNotContainsString('deficiencia_intelectual', $response->getContent());
        $this->assertStringNotContainsString('"conditions"', $response->getContent());
    }
}
