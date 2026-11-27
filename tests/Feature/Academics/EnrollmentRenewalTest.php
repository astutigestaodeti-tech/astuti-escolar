<?php

namespace Tests\Feature\Academics;

use App\Academics\EnrollmentRenewal;
use App\Models\Tenant\AcademicPeriod;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\ClassYearClosure;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentFinalResult;
use App\Models\Tenant\StudentPriorSchoolYear;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class EnrollmentRenewalTest extends TwoMunicipalityTestCase
{
    private string $sourceId;

    private string $yearId;

    private string $nextYearId;

    private string $nextMorningId;

    private string $nextAfternoonId;

    private string $retainedId;

    private string $foreignId;

    private array $entries = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2027-12-18 10:00:00'));
        config()->set('waitlist.cutoff_date', '2028-03-31');
        $this->tenant(function (): void {
            $year = $this->year(2027, 'active');
            $next = $this->year(2028, 'planned');
            $this->yearId = $year->id;
            $this->nextYearId = $next->id;
            $source = $this->room($year, self::ESCOLA_A1, '2º Ano A', 'Manhã', 30);
            $this->sourceId = $source->id;
            $morning = $this->room($next, self::ESCOLA_A1, '3º Ano A', 'Manhã', 1);
            $afternoon = $this->room($next, self::ESCOLA_A1, '3º Ano B', 'Tarde', 30);
            $retained = $this->room($next, self::ESCOLA_A1, '2º Ano A', 'Manhã', 30);
            $foreign = $this->room($next, self::ESCOLA_A2, '3º Ano A', 'Manhã', 30);
            $this->nextMorningId = $morning->id;
            $this->nextAfternoonId = $afternoon->id;
            $this->retainedId = $retained->id;
            $this->foreignId = $foreign->id;

            foreach ([
                'approved' => ['Ana Fictícia Renovação', 'aprovado', 'active'],
                'followup' => ['Bia Fictícia Renovação', 'acompanhado', 'active'],
                'retained' => ['Caio Fictício Renovação', 'retido', 'active'],
                'unchecked' => ['Davi Fictício Renovação', 'aprovado', 'active'],
                'existing' => ['Eva Fictícia Renovação', 'aprovado', 'active'],
                'transferred' => ['Fábio Fictício Renovação', null, 'transferred'],
                'abandoned' => ['Gabi Fictícia Renovação', null, 'abandoned'],
                'deceased' => ['Hugo Fictício Renovação', null, 'deceased'],
                'pending' => ['Iara Fictícia Renovação', 'cursando', 'active'],
            ] as $key => [$name, $result, $status]) {
                $student = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => $name]);
                $entry = $this->enroll($student, $source, $status);
                $this->entries[$key] = $entry->id;
                if ($result !== null) {
                    StudentFinalResult::query()->create([
                        'id' => (string) Str::uuid(), 'school_class_id' => $source->id,
                        'student_enrollment_id' => $entry->id, 'suggested_result' => $result, 'result' => $result,
                        'confirmed_by' => $this->auditFixtureUser->id, 'confirmed_at' => now(),
                    ]);
                }
                if ($key === 'existing') {
                    // A matrícula ativa em OUTRA escola também impede renovação duplicada.
                    $this->enroll($student, $foreign);
                }
            }
            ClassYearClosure::query()->findOrFail($source->id)->forceFill([
                'closed_at' => now(), 'closed_by' => $this->auditFixtureUser->id,
                'close_reason' => 'Fechamento fictício para teste',
            ])->save();
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

    private function year(int $year, string $status): SchoolYear
    {
        return SchoolYear::query()->create([
            'id' => (string) Str::uuid(), 'year' => $year, 'start_date' => $year.'-02-01',
            'end_date' => $year.'-12-18', 'status' => $status,
        ]);
    }

    private function room(SchoolYear $year, string $school, string $name, string $shift, int $capacity, string $stage = 'Ensino Fundamental'): SchoolClass
    {
        return SchoolClass::query()->create([
            'id' => (string) Str::uuid(), 'school_id' => $school, 'school_year_id' => $year->id,
            'name' => $name, 'stage' => $stage, 'modality' => 'Regular',
            'shift' => $shift, 'status' => 'active', 'capacity' => $capacity,
        ]);
    }

    private function enroll(Student $student, SchoolClass $class, string $status = 'active'): StudentEnrollment
    {
        return StudentEnrollment::query()->create([
            'id' => (string) Str::uuid(), 'student_id' => $student->id, 'school_id' => $class->school_id,
            'school_year_id' => $class->school_year_id, 'school_class_id' => $class->id, 'status' => $status,
        ]);
    }

    private function login(string $role, array $schools = [self::ESCOLA_A1], bool $factor = true): void
    {
        $user = $this->criarUsuario(Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schools);
        $this->actingAs($user);
        if ($factor) {
            $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
            $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
            $this->post('/seguranca/segundo-fator/desafio', [
                'code' => app(Totp::class)->at($secret, now()->getTimestamp()),
            ])->assertRedirect();
        }
    }

    private function url(): string
    {
        return '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/turmas/'.$this->sourceId.'/renovacao';
    }

    private function payload(): array
    {
        return [
            'ano_destino' => $this->nextYearId,
            'destinos' => [
                $this->entries['approved'] => $this->nextMorningId,
                $this->entries['followup'] => $this->nextMorningId,
                $this->entries['retained'] => $this->retainedId,
                $this->entries['unchecked'] => null,
            ],
        ];
    }

    public function test_sugestoes_por_serie_turno_resultado_e_matricula_existente(): void
    {
        $this->login('school_secretary');
        $this->get($this->url())->assertOk()->assertSee('Renovar matrículas para 2028')
            ->assertSee('Renovar matrículas da turma')->assertSee('Já renovado');
        $this->tenant(function (): void {
            $data = app(EnrollmentRenewal::class)->preview(SchoolClass::query()->with('schoolYear')->findOrFail($this->sourceId));
            $rows = $data['rows']->keyBy('entry.id');
            $this->assertTrue($data['ready']);
            $this->assertSame($this->nextMorningId, $rows[$this->entries['approved']]['suggestion']);
            $this->assertSame($this->retainedId, $rows[$this->entries['retained']]['suggestion']);
            $this->assertTrue($rows[$this->entries['followup']]['choices']->has($this->nextAfternoonId));
            $this->assertFalse($rows[$this->entries['followup']]['choices']->has($this->foreignId));
            $this->assertSame('renewed', $rows[$this->entries['existing']]['reason']);
            foreach (['transferred', 'abandoned', 'deceased'] as $key) {
                $this->assertSame('exited', $rows[$this->entries[$key]]['reason']);
            }
            $this->assertSame('unconfirmed', $rows[$this->entries['pending']]['reason']);
        });
    }

    public function test_lote_preserva_resultados_e_avisa_capacidade_sem_bloquear_e_sem_repetir(): void
    {
        $this->login('school_director');
        $this->post($this->url(), $this->payload())->assertRedirect($this->url())
            ->assertSessionHas('renewal_summary.renewed', 3)
            ->assertSessionHas('renewal_summary.skipped.unchecked', 1)
            ->assertSessionHas('renewal_summary.skipped.renewed', 1)
            ->assertSessionHas('renewal_summary.warnings.0.occupied', 2);
        $this->get($this->url())->assertOk()->assertSee('Capacidade excedida')->assertSee('3 matrícula(s) renovada(s).');
        $this->post($this->url(), $this->payload())->assertRedirect()
            ->assertSessionHas('renewal_summary.renewed', 0);
        $this->tenant(function (): void {
            $this->assertSame(4, StudentEnrollment::query()->where('school_year_id', $this->nextYearId)->count());
            $this->assertSame(9, StudentEnrollment::query()->where('school_year_id', $this->yearId)->count());
            $this->assertSame(6, StudentFinalResult::query()->where('school_class_id', $this->sourceId)->count());
            $this->assertNotNull(ClassYearClosure::query()->findOrFail($this->sourceId)->closed_at);
            $this->assertSame(9, Student::query()->count());
            $records = StudentEnrollment::query()->where('school_year_id', $this->nextYearId)->where('school_id', self::ESCOLA_A1)->get();
            $this->assertTrue($records->every(fn (StudentEnrollment $entry): bool => $entry->started_at->toDateString() === '2028-02-01'));
            $audit = AuditLog::query()->where('action', 'enrollment.renewal.batch')->get()->first(fn (AuditLog $log): bool => $log->after['renewed_count'] === 3);
            $this->assertNotNull($audit);
            $this->assertCount(3, explode(',', $audit->after['enrollment_ids']));
            $this->assertStringNotContainsString('Fictícia', json_encode($audit->after, JSON_THROW_ON_ERROR));
        });
    }

    public function test_destino_invalido_ou_serie_errada_desfaz_o_lote(): void
    {
        $this->login('school_secretary');
        foreach ([$this->foreignId, $this->retainedId] as $invalid) {
            $payload = $this->payload();
            $payload['destinos'][$this->entries['followup']] = $invalid;
            $this->post($this->url(), $payload)->assertSessionHasErrors('destinos');
            $this->assertSame(1, $this->tenant(fn (): int => StudentEnrollment::query()->where('school_year_id', $this->nextYearId)->count()));
        }
    }

    public function test_ano_desatualizado_e_matricula_fora_da_lista_recusam_lote_sem_gravacao(): void
    {
        $this->login('school_secretary');

        $payload = $this->payload();
        $payload['ano_destino'] = $this->yearId;
        $this->post($this->url(), $payload)->assertSessionHasErrors([
            'destinos' => 'O ano seguinte não está disponível. Consulte novamente.',
        ]);

        $payload = $this->payload();
        $payload['destinos'][(string) Str::uuid()] = $this->nextAfternoonId;
        $this->post($this->url(), $payload)->assertSessionHasErrors([
            'destinos' => 'A lista da turma mudou. Consulte novamente antes de gravar.',
        ]);

        $this->tenant(function (): void {
            $this->assertSame(1, StudentEnrollment::query()->where('school_year_id', $this->nextYearId)->count());
            $this->assertSame(9, StudentEnrollment::query()->where('school_year_id', $this->yearId)->count());
            $this->assertSame(6, StudentFinalResult::query()->where('school_class_id', $this->sourceId)->count());
            $this->assertSame(0, AuditLog::query()->where('action', 'enrollment.renewal.batch')->count());
        });

        $this->post($this->url(), $this->payload())->assertRedirect($this->url())
            ->assertSessionHas('renewal_summary.renewed', 3);
    }

    public function test_cadastro_possivelmente_repetido_fica_para_conferencia_humana(): void
    {
        $this->tenant(function (): void {
            $entry = StudentEnrollment::query()->with('student')->findOrFail($this->entries['approved']);
            Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => Str::ascii(Str::upper($entry->student->full_name))]);
        });
        $this->login('school_secretary');
        $this->post($this->url(), $this->payload())->assertRedirect()
            ->assertSessionHas('renewal_summary.renewed', 2)
            ->assertSessionHas('renewal_summary.skipped.duplicate', 1);
    }

    public function test_turmas_fechadas_nao_sao_destino_e_segundo_fator_deve_ser_recente(): void
    {
        $this->login('school_secretary');
        $this->travel(16)->minutes();
        $this->post($this->url(), $this->payload())->assertRedirect('/seguranca/segundo-fator/desafio');
        $this->assertSame(1, $this->tenant(fn (): int => StudentEnrollment::query()->where('school_year_id', $this->nextYearId)->count()));
        $this->login('school_secretary');
        $this->tenant(function (): void {
            foreach ([$this->nextMorningId, $this->nextAfternoonId, $this->retainedId] as $id) {
                ClassYearClosure::query()->findOrFail($id)->forceFill([
                    'closed_at' => now(), 'closed_by' => $this->auditFixtureUser->id,
                    'close_reason' => 'Fechamento fictício',
                ])->save();
            }
        });
        $this->get($this->url())->assertOk()->assertSee('Não há turmas abertas nesta escola');
        $this->post($this->url(), $this->payload())->assertSessionHasErrors('destinos');
    }

    public function test_pre_condicoes_e_permissoes(): void
    {
        $this->login('teacher');
        $this->get($this->url())->assertForbidden();
        $this->post($this->url(), $this->payload())->assertForbidden();
        $this->login('school_secretary', [self::ESCOLA_A2]);
        $this->get($this->url())->assertForbidden();
        $this->login('school_secretary', [self::ESCOLA_A1], false);
        $this->post($this->url(), $this->payload())->assertForbidden();
        $this->login('school_secretary');
        $this->tenant(function (): void {
            ClassYearClosure::query()->findOrFail($this->sourceId)->forceFill([
                'closed_at' => null, 'closed_by' => null, 'close_reason' => null,
            ])->save();
        });
        $this->get($this->url())->assertOk()->assertSee('ainda não está fechado');
        $this->post($this->url(), $this->payload())->assertSessionHasErrors('destinos');
        $this->tenant(function (): void {
            ClassYearClosure::query()->findOrFail($this->sourceId)->forceFill([
                'closed_at' => now(), 'closed_by' => $this->auditFixtureUser->id,
                'close_reason' => 'Fechamento fictício',
            ])->save();
            SchoolYear::query()->findOrFail($this->nextYearId)->forceFill(['status' => 'completed'])->save();
        });
        $this->get($this->url())->assertOk()->assertSee('não está cadastrado ou está encerrado');
        $this->post($this->url(), $this->payload())->assertSessionHasErrors('destinos');
    }

    private function confirmedEntry(SchoolClass $class, string $name, ?string $birth = null, ?int $code = null, string $result = 'aprovado'): StudentEnrollment
    {
        $student = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => $name, 'birth_date' => $birth]);
        $entry = $this->enroll($student, $class);
        if ($code !== null) {
            $entry->inep_stage_code = $code;
            $entry->save();
        }
        StudentFinalResult::query()->create([
            'id' => (string) Str::uuid(), 'school_class_id' => $class->id, 'student_enrollment_id' => $entry->id,
            'suggested_result' => $result, 'result' => $result, 'confirmed_by' => $this->auditFixtureUser->id, 'confirmed_at' => now(),
        ]);

        return $entry;
    }

    private function closeRoom(SchoolClass $class): void
    {
        ClassYearClosure::query()->findOrFail($class->id)->forceFill([
            'closed_at' => now(), 'closed_by' => $this->auditFixtureUser->id, 'close_reason' => 'Fechamento fictício',
        ])->save();
    }

    public function test_pre_ii_primeiro_ano_e_maternal_respeitam_corte_da_lista_de_espera(): void
    {
        $this->tenant(function (): void {
            $year = SchoolYear::query()->findOrFail($this->yearId);
            $next = SchoolYear::query()->findOrFail($this->nextYearId);
            $pre = $this->room($year, self::ESCOLA_A1, 'Pré II A', 'Manhã', 30, 'Educação Infantil');
            $maternal = $this->room($year, self::ESCOLA_A1, 'Maternal II A', 'Manhã', 30, 'Educação Infantil');
            $first = $this->room($next, self::ESCOLA_A1, '1º Ano A', 'Manhã', 30);
            $preI = $this->room($next, self::ESCOLA_A1, 'Pré I A', 'Manhã', 30, 'Educação Infantil');
            $eligible = $this->confirmedEntry($pre, 'Aluna Fictícia Corte', '2022-03-31');
            $young = $this->confirmedEntry($pre, 'Aluna Fictícia Após Corte', '2022-04-01');
            $unknown = $this->confirmedEntry($pre, 'Aluna Fictícia Sem Data');
            $child = $this->confirmedEntry($maternal, 'Aluna Fictícia Maternal', '2024-03-31');
            $this->closeRoom($pre);
            $this->closeRoom($maternal);
            $service = app(EnrollmentRenewal::class);
            $rows = $service->preview($pre)['rows']->keyBy('entry.id');
            $this->assertSame($first->id, $rows[$eligible->id]['suggestion']);
            $this->assertSame('age_cutoff', $rows[$young->id]['reason']);
            $this->assertSame('age_cutoff', $rows[$unknown->id]['reason']);
            $this->assertSame($preI->id, $service->preview($maternal)['rows']->first()['suggestion']);
            $summary = $service->renew($pre, [$eligible->id => $first->id], $next->id);
            $this->assertSame(1, $summary['renewed']);
            $this->assertNull(StudentEnrollment::query()->where('student_id', $eligible->student_id)->where('school_year_id', $next->id)->sole()->inep_stage_code);
        });
    }

    public function test_multisseriada_usa_codigo_ou_historico_e_grava_etapa_individual_no_destino(): void
    {
        $this->tenant(function (): void {
            $year = SchoolYear::query()->findOrFail($this->yearId);
            $next = SchoolYear::query()->findOrFail($this->nextYearId);
            $source = $this->room($year, self::ESCOLA_A1, 'Multi 1º ao 3º ano', 'Manhã', 30);
            $destination = $this->room($next, self::ESCOLA_A1, 'Multi 2º ao 4º ano', 'Manhã', 1);
            $unknownRoom = $this->room($next, self::ESCOLA_A1, 'Multisseriada sem série', 'Manhã', 30);
            $first = $this->confirmedEntry($source, 'Aluno Fictício Multi A', null, 14);
            $second = $this->confirmedEntry($source, 'Aluno Fictício Multi B', null, null, 'retido');
            $unknown = $this->confirmedEntry($source, 'Aluno Fictício Multi C');
            StudentPriorSchoolYear::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $second->student_id, 'school_year' => 2026,
                'school_stage' => '1º ano do Ensino Fundamental', 'school_name' => 'Escola Fictícia', 'city' => 'Cidade Fictícia', 'state' => 'SP',
                'result' => 'Aprovado', 'recorded_by' => $this->auditFixtureUser->id, 'updated_by' => $this->auditFixtureUser->id,
            ]);
            $this->closeRoom($source);
            $service = app(EnrollmentRenewal::class);
            $rows = $service->preview($source)['rows']->keyBy('entry.id');
            $this->assertSame(2, $rows[$first->id]['target']);
            $this->assertSame(2, $rows[$second->id]['target']);
            $this->assertTrue($rows[$first->id]['choices']->has($destination->id));
            $this->assertFalse($rows[$first->id]['choices']->has($unknownRoom->id));
            $this->assertSame('unknown_grade', $rows[$unknown->id]['reason']);
            $summary = $service->renew($source, [$first->id => $destination->id, $second->id => $destination->id], $next->id);
            $this->assertSame(2, $summary['renewed']);
            $this->assertSame(2, $summary['warnings'][0]['occupied']);
            $this->assertSame([15, 15], StudentEnrollment::query()->where('school_class_id', $destination->id)->orderBy('id')->pluck('inep_stage_code')->all());
            $this->assertNull($second->fresh()->inep_stage_code);
        });
    }

    public function test_outra_escola_requer_escolha_expressa_e_permissao_de_gestao_no_destino(): void
    {
        [$sourceId, $entryId, $destinationId] = $this->tenant(function (): array {
            $year = SchoolYear::query()->findOrFail($this->yearId);
            $next = SchoolYear::query()->findOrFail($this->nextYearId);
            $source = $this->room($year, self::ESCOLA_A1, '5º Ano A', 'Manhã', 30);
            $destination = $this->room($next, self::ESCOLA_A2, '6º Ano A', 'Manhã', 30);
            $entry = $this->confirmedEntry($source, 'Aluno Fictício Outra Unidade');
            $this->closeRoom($source);

            return [$source->id, $entry->id, $destination->id];
        });
        $url = '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/turmas/'.$sourceId.'/renovacao';
        $payload = ['ano_destino' => $this->nextYearId, 'destinos' => [$entryId => $destinationId]];
        $this->login('school_secretary');
        $this->get($url)->assertOk()->assertDontSee($destinationId);
        $this->post($url, $payload)->assertSessionHasErrors('destinos');
        $this->login('school_secretary', [self::ESCOLA_A1, self::ESCOLA_A2]);
        $response = $this->get($url)->assertOk()->assertSee('Sugestão em outra escola');
        $row = $response->viewData('data')['rows']->first();
        $this->assertTrue($row['crossSchool']);
        $this->assertSame($destinationId, $row['suggestion']);
        $this->post($url, ['ano_destino' => $this->nextYearId, 'destinos' => [$entryId => null]])
            ->assertSessionHas('renewal_summary.renewed', 0);
        $this->post($url, $payload)->assertSessionHas('renewal_summary.renewed', 1);
        $this->tenant(function () use ($entryId, $destinationId): void {
            $entry = StudentEnrollment::query()->findOrFail($entryId);
            $renewed = StudentEnrollment::query()->where('student_id', $entry->student_id)->where('school_year_id', $this->nextYearId)->sole();
            $this->assertSame(self::ESCOLA_A2, $renewed->school_id);
            $this->assertSame($destinationId, $renewed->school_class_id);
            $this->assertSame('active', $entry->status);
        });
        $this->post($url, $payload)->assertSessionHas('renewal_summary.renewed', 0);
    }

    public function test_fechamento_infantil_confirma_continuidade_sem_notas_e_sem_retencao(): void
    {
        [$sourceId, $entryId] = $this->tenant(function (): array {
            $year = SchoolYear::query()->findOrFail($this->yearId);
            $source = $this->room($year, self::ESCOLA_A1, 'Pré II B', 'Manhã', 30, 'Educação Infantil');
            $student = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => 'Criança Fictícia Continuidade', 'birth_date' => '2022-03-31']);
            $entry = $this->enroll($student, $source);
            foreach (AcademicPeriod::query()->where('school_year_id', $year->id)->get() as $period) {
                $period->forceFill(['closed_at' => now(), 'closed_by' => $this->auditFixtureUser->id, 'close_reason' => 'Fechamento fictício'])->save();
            }

            return [$source->id, $entry->id];
        });
        $this->login('school_secretary');
        $base = '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/turmas/'.$sourceId;
        $this->get($base.'/resultado-final')->assertOk()->assertSee('Continuidade confirmada');
        $this->post($base.'/resultado-final/confirmar', ['enrollment' => $entryId, 'result' => 'retido', 'reason' => 'Motivo fictício de teste'])->assertSessionHasErrors('result');
        $this->post($base.'/resultado-final/confirmar', ['all' => '1'])->assertRedirect();
        $this->post($base.'/resultado-final/fechar', ['reason' => 'Fechamento fictício de turma'])->assertRedirect();
        $this->tenant(function () use ($sourceId, $entryId): void {
            $this->assertNotNull(ClassYearClosure::query()->findOrFail($sourceId)->closed_at);
            $this->assertSame('aprovado', StudentFinalResult::query()->where('student_enrollment_id', $entryId)->sole()->result);
        });
    }
}
