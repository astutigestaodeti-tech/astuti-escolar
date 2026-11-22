<?php

namespace Tests\Feature\Schools;

use App\Models\Tenant\AttendanceMark;
use App\Models\Tenant\AttendanceSession;
use App\Models\Tenant\Guardian;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentGuardian;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class SchoolAbsenceAlertsTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private string $yearId;

    private string $classId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2027-04-15 09:00:00'));

        [$this->yearId, $this->classId] = $this->inMunicipality(function (): array {
            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(),
                'year' => 2027,
                'start_date' => '2027-02-01',
                'end_date' => '2027-12-20',
                'status' => 'active',
            ]);
            $class = SchoolClass::query()->create([
                'id' => (string) Str::uuid(),
                'school_id' => self::ESCOLA_A1,
                'school_year_id' => $year->id,
                'name' => '4º Ano A',
                'stage' => 'Ensino Fundamental',
                'modality' => 'Regular',
                'shift' => 'Manhã',
                'status' => 'active',
            ]);

            return [$year->id, $class->id];
        });
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function inMunicipality(callable $callback): mixed
    {
        return app(TenantContext::class)->runFor($this->municipioA, $callback);
    }

    private function login(string $role, array $schools = [self::ESCOLA_A1]): void
    {
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schools);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp()),
        ])->assertRedirect();
    }

    private function url(string $school = self::ESCOLA_A1): string
    {
        return '/m/municipio-a/escolas/'.$school.'/alertas-faltas';
    }

    private function schoolUrl(string $school = self::ESCOLA_A1): string
    {
        return '/m/municipio-a/escolas/'.$school;
    }

    private function addClass(string $name, string $school = self::ESCOLA_A1): string
    {
        return $this->inMunicipality(fn (): string => SchoolClass::query()->create([
            'id' => (string) Str::uuid(),
            'school_id' => $school,
            'school_year_id' => $this->yearId,
            'name' => $name,
            'stage' => 'Ensino Fundamental',
            'modality' => 'Regular',
            'shift' => 'Tarde',
            'status' => 'active',
        ])->id);
    }

    private function addStudent(string $name, ?string $classId = null, ?string $phone = null, string $school = self::ESCOLA_A1, string $status = 'active'): string
    {
        return $this->inMunicipality(function () use ($name, $classId, $phone, $school, $status): string {
            $student = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => $name]);
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $student->id,
                'school_id' => $school,
                'school_year_id' => $this->yearId,
                'school_class_id' => $classId ?? $this->classId,
                'status' => $status,
            ]);

            if ($phone !== null) {
                $guardian = Guardian::query()->create([
                    'id' => (string) Str::uuid(),
                    'full_name' => 'Contato Fictício da Família',
                    'phone' => $phone,
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

    private function mark(string $day, array $statuses, ?string $classId = null): void
    {
        $this->inMunicipality(function () use ($day, $statuses, $classId): void {
            $session = AttendanceSession::query()->create([
                'id' => (string) Str::uuid(),
                'school_class_id' => $classId ?? $this->classId,
                'attendance_date' => $day,
                'recorded_by' => $this->auditFixtureUser->id,
                'recorded_at' => now(),
            ]);
            foreach ($statuses as $studentId => $status) {
                AttendanceMark::query()->create([
                    'id' => (string) Str::uuid(),
                    'attendance_session_id' => $session->id,
                    'student_id' => $studentId,
                    'status' => $status,
                ]);
            }
        });
    }

    public function test_contagem_no_cartao_e_alerta_por_tres_faltas_consecutivas_com_contato(): void
    {
        $student = $this->addStudent('Álvaro Fictício', phone: '11987654321');
        $this->mark('2027-04-01', [$student => 'present']);
        foreach (['2027-04-12', '2027-04-13', '2027-04-14'] as $day) {
            $this->mark($day, [$student => 'absent']);
        }
        $this->login('school_secretary');

        $this->get($this->schoolUrl())->assertOk()
            ->assertSee('Alerta de faltas')
            ->assertSee('1 aluno em alerta')
            ->assertSee($this->url());
        $this->get($this->url())->assertOk()
            ->assertSee('Álvaro Fictício')
            ->assertSee('4º Ano A')
            ->assertSee('<span class="alerta-faltas-etiqueta">3 ou mais faltas seguidas</span>', false)
            ->assertSee('25%')
            ->assertSee('3 seguidas')
            ->assertSee('01/04/2027')
            ->assertSee('href="tel:+5511987654321"', false)
            ->assertSee('href="https://wa.me/5511987654321"', false)
            ->assertSee('/alunos/'.$student);
    }

    public function test_alerta_mensal_abaixo_75_sem_disparar_alerta_anual_ou_sequencia(): void
    {
        $student = $this->addStudent('Bruna Fictícia');
        for ($day = 1; $day <= 9; $day++) {
            $this->mark(sprintf('2027-02-%02d', $day), [$student => 'present']);
        }
        $this->mark('2027-04-01', [$student => 'present']);
        $this->mark('2027-04-02', [$student => 'absent']);
        $this->mark('2027-04-03', [$student => 'absent']);
        $this->login('school_director');

        $this->get($this->url())->assertOk()
            ->assertSee('Bruna Fictícia')
            ->assertSee('33%')
            ->assertSee('83%')
            ->assertSee('<span class="alerta-faltas-etiqueta">Abaixo de 75% no mês</span>', false)
            ->assertDontSee('<span class="alerta-faltas-etiqueta">Abaixo de 75% no ano</span>', false)
            ->assertDontSee('<span class="alerta-faltas-etiqueta">3 ou mais faltas seguidas</span>', false);
    }

    public function test_alerta_anual_abaixo_75_mas_mes_atual_integral(): void
    {
        $student = $this->addStudent('Carlos Fictício');
        $this->mark('2027-02-01', [$student => 'present']);
        foreach (['2027-02-02', '2027-02-03', '2027-02-04', '2027-02-05'] as $day) {
            $this->mark($day, [$student => 'absent']);
        }
        foreach (['2027-04-01', '2027-04-02', '2027-04-03', '2027-04-04'] as $day) {
            $this->mark($day, [$student => 'present']);
        }
        $this->login('school_secretary');

        $this->get($this->url())->assertOk()
            ->assertSee('Carlos Fictício')
            ->assertSee('100%')
            ->assertSee('56%')
            ->assertSee('<span class="alerta-faltas-etiqueta">Abaixo de 75% no ano</span>', false)
            ->assertDontSee('<span class="alerta-faltas-etiqueta">Abaixo de 75% no mês</span>', false)
            ->assertDontSee('<span class="alerta-faltas-etiqueta">3 ou mais faltas seguidas</span>', false);
    }

    public function test_faltas_justificadas_nao_geram_alerta_e_contam_em_coluna_separada(): void
    {
        $ana = $this->addStudent('Ana Fictícia');
        $bia = $this->addStudent('Bia Fictícia');
        foreach (['2027-04-02', '2027-04-03'] as $day) {
            $this->mark($day, [$ana => 'justified', $bia => 'present']);
        }
        foreach (['2027-04-10', '2027-04-11', '2027-04-12'] as $day) {
            $this->mark($day, [$ana => 'absent', $bia => 'justified']);
        }
        $this->login('school_secretary');

        $this->get($this->schoolUrl())->assertOk()->assertSee('1 aluno em alerta');
        $this->get($this->url())->assertOk()
            ->assertSee('Ana Fictícia')
            ->assertDontSee('Bia Fictícia')
            ->assertSee('3 seguidas')
            ->assertSee('2 justificadas')
            ->assertSee('<span class="alerta-faltas-etiqueta">3 ou mais faltas seguidas</span>', false);
    }

    public function test_falta_justificada_entre_ausencias_interrompe_a_sequencia(): void
    {
        $student = $this->addStudent('Estudante Fictício');
        $this->mark('2027-04-01', [$student => 'absent']);
        $this->mark('2027-04-02', [$student => 'justified']);
        $this->mark('2027-04-03', [$student => 'absent']);
        $this->mark('2027-04-04', [$student => 'absent']);
        $this->login('school_secretary');

        $this->get($this->url())->assertOk()
            ->assertSee('Estudante Fictício')
            ->assertSee('2 seguidas')
            ->assertSee('1 justificada')
            ->assertDontSee('<span class="alerta-faltas-etiqueta">3 ou mais faltas seguidas</span>', false);
    }

    public function test_filtro_por_turma_motivo_e_ordenacao_por_gravidade(): void
    {
        $otherClass = $this->addClass('5º Ano B');
        $severe = $this->addStudent('Alfa Fictícia');
        $medium = $this->addStudent('Beta Fictícia', classId: $otherClass);
        $monthly = $this->addStudent('Gama Fictícia');
        foreach (['2027-04-10', '2027-04-11', '2027-04-12', '2027-04-13'] as $day) {
            $this->mark($day, [$severe => 'absent', $monthly => $day === '2027-04-13' ? 'present' : 'absent']);
        }
        foreach (['2027-04-10', '2027-04-11', '2027-04-12'] as $day) {
            $this->mark($day, [$medium => 'absent'], $otherClass);
        }
        $this->login('school_secretary');

        $this->get($this->url())->assertOk()
            ->assertSeeInOrder(['Alfa Fictícia', 'Beta Fictícia', 'Gama Fictícia']);
        $this->get($this->url().'?turma='.$otherClass)->assertOk()
            ->assertSee('Beta Fictícia')
            ->assertDontSee('Alfa Fictícia')
            ->assertDontSee('Gama Fictícia');
        $this->get($this->url().'?motivo=sequencia')->assertOk()
            ->assertSee('Alfa Fictícia')
            ->assertSee('Beta Fictícia')
            ->assertDontSee('Gama Fictícia');
        $this->get($this->url().'?motivo=mes')->assertOk()
            ->assertSee('Gama Fictícia');
    }

    public function test_professor_nao_ve_cartao_e_nao_acesse_painel_mas_gestao_acessa(): void
    {
        $this->login('teacher');
        $this->get($this->schoolUrl())->assertOk()->assertDontSee('Alerta de faltas');
        $this->get($this->url())->assertForbidden();

        $this->login('school_secretary');
        $this->get($this->url())->assertOk();

        $this->login('school_director');
        $this->get($this->url())->assertOk();
    }

    public function test_isolamento_de_outra_escola_no_painel_contagem_e_filtro(): void
    {
        $otherClass = $this->addClass('Turma Outra Unidade', self::ESCOLA_A2);
        $student = $this->addStudent('Aluno de Outra Escola', classId: $otherClass, school: self::ESCOLA_A2);
        foreach (['2027-04-10', '2027-04-11', '2027-04-12'] as $day) {
            $this->mark($day, [$student => 'absent'], $otherClass);
        }
        $this->login('school_secretary', [self::ESCOLA_A1]);

        $this->get($this->schoolUrl())->assertOk()->assertSee('0 alunos em alerta');
        $this->get($this->url())->assertOk()->assertDontSee('Aluno de Outra Escola');
        $this->get($this->url(self::ESCOLA_A2))->assertForbidden();
        $this->get($this->url().'?turma='.$otherClass)->assertSessionHasErrors('turma');
    }

    public function test_matricula_encerrada_nao_gera_alerta_da_escola(): void
    {
        $student = $this->addStudent('Matrícula Fictícia Encerrada', status: 'transferred');
        foreach (['2027-04-10', '2027-04-11', '2027-04-12'] as $day) {
            $this->mark($day, [$student => 'absent']);
        }
        $this->login('school_secretary');

        $this->get($this->schoolUrl())->assertOk()->assertSee('0 alunos em alerta');
        $this->get($this->url())->assertOk()->assertDontSee('Matrícula Fictícia Encerrada');
    }

    public function test_sem_chamada_nao_cria_alerta_e_nao_expoe_dados_de_saude(): void
    {
        $this->addStudent('Pessoa Sem Chamada');
        $this->login('school_secretary');

        $this->get($this->schoolUrl())->assertOk()->assertSee('0 alunos em alerta');
        $this->get($this->url())->assertOk()
            ->assertSee('Nenhum aluno em alerta')
            ->assertDontSee('Observação');
    }

    public function test_consulta_com_mil_alunos_nao_faz_consulta_por_aluno(): void
    {
        $rows = [];
        $enrollments = [];
        $marks = [];
        $sessionId = (string) Str::uuid();
        $now = now()->toDateTimeString();
        for ($i = 0; $i < 1000; $i++) {
            $id = (string) Str::uuid();
            $rows[] = ['id' => $id, 'full_name' => 'Estudante Fictício '.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'created_at' => $now, 'updated_at' => $now];
            $enrollments[] = [
                'id' => (string) Str::uuid(), 'student_id' => $id,
                'school_id' => self::ESCOLA_A1, 'school_year_id' => $this->yearId,
                'school_class_id' => $this->classId, 'status' => 'active',
                'created_at' => $now, 'updated_at' => $now,
            ];
            $marks[] = [
                'id' => (string) Str::uuid(), 'attendance_session_id' => $sessionId,
                'student_id' => $id, 'status' => 'present',
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        $this->inMunicipality(function () use ($rows, $enrollments, $marks, $sessionId): void {
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::connection('tenant')->table('students')->insert($chunk);
            }
            foreach (array_chunk($enrollments, 500) as $chunk) {
                DB::connection('tenant')->table('student_enrollments')->insert($chunk);
            }
            AttendanceSession::query()->create([
                'id' => $sessionId, 'school_class_id' => $this->classId,
                'attendance_date' => '2027-04-14',
                'recorded_by' => $this->auditFixtureUser->id, 'recorded_at' => now(),
            ]);
            foreach (array_chunk($marks, 500) as $chunk) {
                DB::connection('tenant')->table('attendance_marks')->insert($chunk);
            }
        });
        $this->login('school_director');
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            if ($query->connectionName === 'tenant') {
                $queries[] = $query->sql;
            }
        });

        $this->get($this->url())->assertOk()->assertSee('Nenhum aluno em alerta');
        $this->assertLessThanOrEqual(10, count($queries), 'A consulta de mil alunos deve ter quantidade fixa de queries.');
    }
}
